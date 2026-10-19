<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftView;
use Draft\Contracts\DraftViewInterface;

/**
 * DraftViewTest - Tests for DraftView
 *
 * @phpstan-import-type DraftClassPlayerRow from \Draft\Contracts\DraftRepositoryInterface
 */
class DraftViewTest extends TestCase
{
    private DraftView $view;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->view = new DraftView();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * @param array<string, int|string|float|null> $overrides
     * @return DraftClassPlayerRow
     */
    private function makePlayer(array $overrides = []): array
    {
        $defaults = [
            'id' => 1, 'name' => 'Test Player', 'pos' => 'PG', 'team' => 'Boston',
            'team_tid' => 5, 'color1' => '008348', 'color2' => 'BB9753', 'drafted' => 0,
            'age' => 22, 'fga' => 50, 'fgp' => 51, 'fta' => 52, 'ftp' => 53,
            'r_3ga' => 54, 'r_3gp' => 55, 'orb' => 56, 'drb' => 57, 'ast' => 58,
            'stl' => 59, 'tvr' => 60, 'blk' => 61, 'oo' => 62, 'r_drive_off' => 63,
            'po' => 64, 'r_trans_off' => 65, 'od' => 66, 'dd' => 67, 'pd' => 68,
            'td' => 69, 'talent' => 70, 'skill' => 71, 'intangibles' => 72,
            'ranking' => 1.0, 'invite' => null, 'sta' => 50,
        ];

        /** @var DraftClassPlayerRow */
        return array_replace($defaults, $overrides);
    }

    // ============================================
    // RENDER VALIDATION ERROR TESTS
    // ============================================

    public function testRenderValidationErrorReturnsString(): void
    {
        $result = $this->view->renderValidationError('Test error');

        $this->assertIsString($result);
    }

    public function testRenderValidationErrorContainsErrorMessage(): void
    {
        $result = $this->view->renderValidationError('Player not found');

        $this->assertStringContainsString('Player not found', $result);
    }

    public function testRenderValidationErrorContainsDraftModuleLink(): void
    {
        $result = $this->view->renderValidationError('Test error');

        $this->assertStringContainsString('modules.php?name=Draft', $result);
    }

    public function testRenderValidationErrorEscapesHtml(): void
    {
        $result = $this->view->renderValidationError('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result);
    }

    // ============================================
    // RENDER DRAFT INTERFACE TESTS
    // ============================================

    public function testRenderDraftInterfaceReturnsString(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertIsString($result);
    }

    public function testRenderDraftInterfaceAcceptsSeasonYear(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        // seasonYear is accepted as a parameter but not rendered in the view output
        $this->assertIsString($result);
    }

    public function testRenderDraftInterfaceContainsTeamLogo(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 5);

        $this->assertStringContainsString('images/logo/5.jpg', $result);
    }

    public function testRenderDraftInterfaceContainsForm(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('</form>', $result);
    }

    public function testRenderDraftInterfaceContainsHiddenFields(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertStringContainsString("type='hidden'", $result);
        $this->assertStringContainsString('draft_round', $result);
        $this->assertStringContainsString('draft_pick', $result);
    }

    public function testRenderDraftInterfaceContainsCsrfToken(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        // The draft form must carry a CSRF token so the gated op=select handler
        // (modules/Draft/index.php) can validate the submission.
        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderDraftInterfaceAcceptsNullPickState(): void
    {
        $players = [[
            'id' => 1, 'name' => 'Rookie One', 'pos' => 'PG', 'age' => 20, 'team' => 'Nowhere',
            'fga' => 1, 'fgp' => 1, 'fta' => 1, 'ftp' => 1, 'r_3ga' => 1, 'r_3gp' => 1,
            'orb' => 1, 'drb' => 1, 'ast' => 1, 'stl' => 1, 'tvr' => 1, 'blk' => 1,
            'oo' => 1, 'r_drive_off' => 1, 'po' => 1, 'r_trans_off' => 1, 'od' => 1,
            'dd' => 1, 'pd' => 1, 'td' => 1, 'talent' => 1, 'skill' => 1, 'intangibles' => 1,
            'ranking' => null, 'invite' => null, 'drafted' => 0, 'sta' => null,
            'team_tid' => null, 'color1' => null, 'color2' => null,
        ]];

        $owned = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);
        $this->assertStringContainsString('Draft Player', $owned);

        $noPick = $this->view->renderDraftInterface($players, 'TestTeam', null, null, null, 2025, 1);
        $this->assertStringNotContainsString('Draft Player', $noPick);
    }

    // ============================================
    // RENDER PLAYER TABLE CHARACTERIZATION TESTS
    // ============================================

    public function testRenderPlayerTableEmptyListRendersHeaderAndEmptyBody(): void
    {
        $html = $this->view->renderPlayerTable([], 'Boston', 'Boston');

        $this->assertSame($this->expectedEmpty(), $html);
        $this->assertStringEndsWith('<tbody></tbody></table></div></div>', $html);
    }

    public function testRenderPlayerTableDraftedRowHasDraftedClassAndEmptyDraftCell(): void
    {
        $html = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 1])], 'Boston', 'Boston');

        $this->assertSame($this->expectedDrafted(), $html);
        $this->assertStringContainsString('<tr class="drafted">', $html);
        $this->assertStringNotContainsString('type="radio"', $html);
    }

    public function testRenderPlayerTableOwnPickUndraftedRowRendersRadio(): void
    {
        $html = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 0])], 'Boston', 'Boston');

        $this->assertSame($this->expectedOwnPickZero(), $html);
        $this->assertStringContainsString('<input type="radio" name="player" value="Test Player" aria-label="Test Player">', $html);
        $this->assertStringContainsString('<tr>', $html);
    }

    public function testRenderPlayerTableOwnPickNullDraftedRowRendersRadio(): void
    {
        $html = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => null])], 'Boston', 'Boston');

        $this->assertSame($this->expectedOwnPickZero(), $html);
    }

    public function testRenderPlayerTableNonOwnerUndraftedRowHasNoRadio(): void
    {
        // Viewing team and pick owner differ ('Boston','Chicago'): no radio, empty sticky-col cell.
        $html = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 0])], 'Boston', 'Chicago');

        $this->assertSame($this->expectedNonOwner(), $html);
        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertStringContainsString('<td class="sticky-col"></td>', $html);
    }

    public function testRenderPlayerTableNullPickOwnerRendersNoRadio(): void
    {
        $html = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 0])], 'Boston', null);

        $this->assertSame($this->expectedNonOwner(), $html);
    }

    public function testRenderPlayerTableNullTeamTidRendersPlainEscapedTeamCell(): void
    {
        $html = $this->view->renderPlayerTable(
            [$this->makePlayer(['team_tid' => null, 'team' => 'FA & Co'])],
            'Boston',
            'Boston'
        );

        $this->assertSame($this->expectedNullTeamTid(), $html);
        $this->assertStringContainsString('<td>FA &amp; Co</td>', $html);
        $this->assertStringNotContainsString('images/logo/', $html);
    }

    public function testRenderPlayerTableNonNullTeamTidRendersTeamCellHelper(): void
    {
        $html = $this->view->renderPlayerTable([$this->makePlayer()], 'Boston', 'Boston');

        $this->assertSame($this->expectedOwnPickZero(), $html);
        $this->assertStringContainsString('images/logo/new5.png', $html);
    }

    public function testRenderPlayerTableNullColorsFallBackToDefaults(): void
    {
        $html = $this->view->renderPlayerTable(
            [$this->makePlayer(['color1' => null, 'color2' => null])],
            'Boston',
            'Boston'
        );

        $this->assertSame($this->expectedNullColors(), $html);
        $this->assertStringContainsString('FFFFFF', $html);
        $this->assertStringContainsString('000000', $html);
    }

    public function testRenderPlayerTableEscapesScriptAndQuotesInRadioAndNameCell(): void
    {
        $html = $this->view->renderPlayerTable(
            [$this->makePlayer(['name' => '<script>alert("x")</script> O\'Neil'])],
            'Boston',
            'Boston'
        );

        $this->assertSame($this->expectedEscaping(), $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('value="<script>', $html);
        // value=, aria-label, and the name cell
        $this->assertSame(3, substr_count($html, '&lt;script&gt;'));
        $this->assertSame(6, substr_count($html, '&quot;'));
        $this->assertSame(3, substr_count($html, 'O&apos;Neil'));
    }

    public function testRenderPlayerTableDraftedOneAndOtherNonNullDraftedRenderIdentically(): void
    {
        $html1 = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 1])], 'Boston', 'Boston');
        $html2 = $this->view->renderPlayerTable([$this->makePlayer(['drafted' => 2])], 'Boston', 'Boston');

        $this->assertSame($html1, $html2);
        $this->assertStringContainsString('class="drafted"', $html1);
        $this->assertStringContainsString('class="drafted"', $html2);
    }

    public function testRenderPlayerTableRendersRowsInInputOrder(): void
    {
        $html = $this->view->renderPlayerTable(
            [
                $this->makePlayer(['name' => 'First', 'drafted' => 1]),
                $this->makePlayer(['name' => 'Second', 'drafted' => 0]),
            ],
            'Boston',
            'Boston'
        );

        $this->assertSame($this->expectedTwoRows(), $html);
        $this->assertLessThan(strpos($html, 'Second'), strpos($html, 'First'));
        $this->assertSame(1, substr_count($html, 'type="radio"'));
    }

    public function testRenderPlayerTablePublicSignatureIsUnchanged(): void
    {
        $method = new \ReflectionMethod(DraftView::class, 'renderPlayerTable');

        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('array', (string) $params[0]->getType());
        $this->assertSame('string', (string) $params[1]->getType());
        $this->assertSame('?string', (string) $params[2]->getType());
        $this->assertTrue($params[2]->allowsNull());
        $this->assertSame('string', (string) $method->getReturnType());
    }
    public function testDraftViewHoldsNoStateOrCollaborators(): void
    {
        $class = new \ReflectionClass(DraftView::class);

        $this->assertSame([], $class->getProperties(), 'DraftView must declare no properties (no DB handle, no session, no actor identity)');
        $this->assertNull($class->getConstructor(), 'DraftView must have no constructor (no injected collaborators)');
    }

    public function testDraftViewPublicMethodsAllBelongToTheInterface(): void
    {
        $class = new \ReflectionClass(DraftView::class);
        $interface = new \ReflectionClass(DraftViewInterface::class);

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertTrue(
                $interface->hasMethod($method->getName()),
                "Public method {$method->getName()} is not declared on DraftViewInterface; new helpers must be private"
            );
        }
    }

    public function testRenderPlayerRowAndStatColumnsArePrivate(): void
    {
        $method = new \ReflectionMethod(DraftView::class, 'renderPlayerRow');
        $this->assertTrue($method->isPrivate());

        $constant = new \ReflectionClassConstant(DraftView::class, 'STAT_COLUMNS');
        $this->assertTrue($constant->isPrivate());

        $columns = $constant->getValue();
        $this->assertIsArray($columns);
        $this->assertCount(24, $columns);
        $this->assertSame($columns, array_values(array_unique($columns)), 'STAT_COLUMNS must have no duplicate keys');
        $this->assertSame('age', $columns[0]);
        $this->assertSame('intangibles', $columns[23]);
    }

    // ============================================
    // GOLDEN HTML (captured from the pre-refactor renderer)
    // ============================================

    private function expectedEmpty(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody></tbody></table></div></div>';
    }

    private function expectedDrafted(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr class="drafted">
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2">Test Player</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedOwnPickZero(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr>
                    <td class="sticky-col"><input type="radio" name="player" value="Test Player" aria-label="Test Player"></td>
                    <td class="sticky-col-2">Test Player</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedNonOwner(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr>
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2">Test Player</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedNullTeamTid(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr>
                    <td class="sticky-col"><input type="radio" name="player" value="Test Player" aria-label="Test Player"></td>
                    <td class="sticky-col-2">Test Player</td>
            <td>PG</td><td>FA &amp; Co</td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedNullColors(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr>
                    <td class="sticky-col"><input type="radio" name="player" value="Test Player" aria-label="Test Player"></td>
                    <td class="sticky-col-2">Test Player</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #FFFFFF; --team-cell-color: #000000;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedEscaping(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr>
                    <td class="sticky-col"><input type="radio" name="player" value="&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; O&apos;Neil" aria-label="&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; O&apos;Neil"></td>
                    <td class="sticky-col-2">&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; O&apos;Neil</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }

    private function expectedTwoRows(): string
    {
        return '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody><tr class="drafted">
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2">First</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr><tr>
                    <td class="sticky-col"><input type="radio" name="player" value="Second" aria-label="Second"></td>
                    <td class="sticky-col-2">Second</td>
            <td>PG</td><td class="ibl-team-cell--colored" style="--team-cell-bg: #008348; --team-cell-color: #ffffff;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name" aria-label="Boston"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Boston</span></a></td>
            <td>22</td>
            <td>50</td>
            <td>51</td>
            <td>52</td>
            <td>53</td>
            <td>54</td>
            <td>55</td>
            <td>56</td>
            <td>57</td>
            <td>58</td>
            <td>59</td>
            <td>60</td>
            <td>61</td>
            <td>62</td>
            <td>63</td>
            <td>64</td>
            <td>65</td>
            <td>66</td>
            <td>67</td>
            <td>68</td>
            <td>69</td>
            <td>70</td>
            <td>71</td>
            <td>72</td>
            </tr></tbody></table></div></div>';
    }
}
