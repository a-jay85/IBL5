<?php

declare(strict_types=1);

namespace Api\Repository;

/**
 * Liveness probe for the API health endpoint.
 *
 * Wraps the connectivity check behind the repository boundary so that
 * HealthController remains agnostic of the raw mysqli connection and the
 * ibl.directMysqliQuery PHPStan rule is satisfied.
 */
class HealthRepository extends \BaseMysqliRepository
{
    /**
     * Return true when the database answers a lightweight SELECT 1 probe.
     *
     * Uses fetchOne() (a prepared statement internally) rather than
     * $this->db->query() directly.
     */
    public function isReachable(): bool
    {
        try {
            $this->fetchOne('SELECT 1 AS ok');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
