<?php

declare(strict_types=1);

namespace Tests\Http;

use Http\HttpRequest;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes Http\HttpRequest as an exact, zero-sanitization passthrough over
 * the four request superglobals, and pins the statelessness invariant that makes
 * it safe to inject into every controller.
 */
final class HttpRequestTest extends TestCase
{
    /**
     * Names that would betray the value object holding a database handle, a
     * session, or an actor identity.
     *
     * @var list<string>
     */
    private const FORBIDDEN_NAMES = [
        'db',
        'mysqli',
        'pdo',
        'conn',
        'connection',
        'session',
        'user',
        'userid',
        'loggedinteamid',
        'teamid',
        'authservice',
    ];

    // ── Passthrough ───────────────────────────────────────────────────────────

    public function testFromGlobalsCapturesAllFourSuperglobalArraysUnchanged(): void
    {
        $_GET['httpRequestTestKey'] = 'gv';
        $_POST['httpRequestTestKey'] = 'pv';
        $_REQUEST['httpRequestTestKey'] = 'rv';
        $_SERVER['httpRequestTestKey'] = 'sv';

        try {
            $request = HttpRequest::fromGlobals();

            self::assertSame('gv', $request->get('httpRequestTestKey'));
            self::assertSame('pv', $request->post('httpRequestTestKey'));
            self::assertSame('rv', $request->request('httpRequestTestKey'));
            self::assertSame('sv', $request->server('httpRequestTestKey'));
            self::assertSame($_GET, $request->allGet());
            self::assertSame($_POST, $request->allPost());
        } finally {
            unset(
                $_GET['httpRequestTestKey'],
                $_POST['httpRequestTestKey'],
                $_REQUEST['httpRequestTestKey'],
                $_SERVER['httpRequestTestKey'],
            );
        }
    }

    public function testGetReturnsStoredQueryValue(): void
    {
        $request = new HttpRequest(get: ['result' => 'rookie_option_success']);

        self::assertSame('rookie_option_success', $request->get('result'));
    }

    public function testPostReturnsStoredBodyValue(): void
    {
        $request = new HttpRequest(post: ['Action' => 'waive']);

        self::assertSame('waive', $request->post('Action'));
    }

    public function testRequestReturnsStoredRequestValueAndNotAGetPostMerge(): void
    {
        $request = new HttpRequest(
            get: ['onlyInGet' => 'g'],
            post: ['onlyInPost' => 'p'],
            request: ['onlyInRequest' => 'r'],
        );

        self::assertSame('r', $request->request('onlyInRequest'));
        self::assertNull($request->request('onlyInGet'));
        self::assertNull($request->request('onlyInPost'));
    }

    public function testServerReturnsStoredServerValue(): void
    {
        $request = new HttpRequest(server: ['REQUEST_METHOD' => 'POST']);

        self::assertSame('POST', $request->server('REQUEST_METHOD'));
    }

    public function testAllGetReturnsSuppliedQueryArrayVerbatim(): void
    {
        $query = ['pid' => '42', 'pageView' => '2'];

        self::assertSame($query, (new HttpRequest(get: $query))->allGet());
    }

    public function testAllPostReturnsSuppliedBodyArrayVerbatim(): void
    {
        $body = ['offer1' => '101', 'offer2' => '', 'Action' => 'add'];

        self::assertSame($body, (new HttpRequest(post: $body))->allPost());
    }

    // ── Negative / boundary ───────────────────────────────────────────────────

    public function testAccessorsReturnNullForAbsentKey(): void
    {
        $request = new HttpRequest(
            get: ['present' => 'g'],
            post: ['present' => 'p'],
            request: ['present' => 'r'],
            server: ['present' => 's'],
        );

        self::assertNull($request->get('absent'));
        self::assertNull($request->post('absent'));
        self::assertNull($request->request('absent'));
        self::assertNull($request->server('absent'));
    }

    public function testAccessorsReturnNullForEmptyStringKey(): void
    {
        $request = new HttpRequest(get: ['present' => 'g']);

        self::assertNull($request->get(''));
        self::assertNull($request->post(''));
        self::assertNull($request->request(''));
        self::assertNull($request->server(''));
    }

    public function testArrayValuedParameterIsReturnedUnchangedWithoutCoercion(): void
    {
        $request = new HttpRequest(get: ['display' => ['x']]);

        self::assertSame(['x'], $request->get('display'));
    }

    public function testConstructorDefaultsProduceEmptyArraysForEveryAccessor(): void
    {
        $request = new HttpRequest();

        self::assertNull($request->get('anything'));
        self::assertNull($request->post('anything'));
        self::assertNull($request->request('anything'));
        self::assertNull($request->server('anything'));
        self::assertSame([], $request->allGet());
        self::assertSame([], $request->allPost());
    }

    // ── Statelessness invariant ───────────────────────────────────────────────

    public function testHttpRequestIsStateless(): void
    {
        $names = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass(HttpRequest::class))->getProperties(),
        );
        sort($names);

        self::assertSame([], $this->statelessnessViolations(HttpRequest::class));
        self::assertSame(['get', 'post', 'request', 'server'], $names);
    }

    public function testHttpRequestStatelessFailsWithForbiddenProperty(): void
    {
        $stateful = new class {
            private ?\mysqli $db = null;

            /** Reads and writes the handle so it is a genuine piece of state. */
            public function swapDatabase(\mysqli $db): ?\mysqli
            {
                $previous = $this->db;
                $this->db = $db;

                return $previous;
            }
        };

        $violations = $this->statelessnessViolations($stateful);

        self::assertNotEmpty($violations);
        self::assertStringContainsString('db', implode("\n", $violations));
    }

    /**
     * Every way the statelessness invariant can be broken, as a list of reasons.
     *
     * @param object|class-string $classOrObject
     * @return list<string>
     */
    private function statelessnessViolations(object|string $classOrObject): array
    {
        $reflection = new \ReflectionClass($classOrObject);
        $violations = [];

        foreach ($reflection->getProperties() as $property) {
            if (in_array(strtolower($property->getName()), self::FORBIDDEN_NAMES, true)) {
                $violations[] = 'forbidden property name: ' . $property->getName();
            }

            $type = $property->getType();
            $isPlainArray = $type instanceof \ReflectionNamedType
                && $type->getName() === 'array'
                && !$type->allowsNull();

            if (!$isPlainArray) {
                $violations[] = 'property ' . $property->getName() . ' is not typed as a plain array';
            }
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if (in_array(strtolower($parameter->getName()), self::FORBIDDEN_NAMES, true)) {
                    $violations[] = 'forbidden constructor parameter: ' . $parameter->getName();
                }
            }
        }

        return $violations;
    }
}
