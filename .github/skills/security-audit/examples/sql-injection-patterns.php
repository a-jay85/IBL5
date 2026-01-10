<?php

/**
 * SQL Injection Vulnerability Patterns - Before and After
 *
 * This file demonstrates common SQL injection vulnerabilities and their fixes.
 */

// ============================================
// EXAMPLE 1: String Interpolation
// ============================================

// ❌ VULNERABLE - Direct variable interpolation
function findPlayerVulnerable($db, int $id): ?array
{
    $query = "SELECT * FROM ibl_plr WHERE pid = $id";
    $result = $db->sql_query($query);
    return $db->sql_fetchrow($result);
}

// ✅ SECURE - Prepared statement
function findPlayerSecure(\mysqli $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM ibl_plr WHERE pid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}


// ============================================
// EXAMPLE 2: String Concatenation with User Input
// ============================================

// ❌ VULNERABLE
function searchPlayersVulnerable($db, string $name): array
{
    $query = "SELECT * FROM ibl_plr WHERE name LIKE '%" . $name . "%'";
    $result = $db->sql_query($query);
    $players = [];
    while ($row = $db->sql_fetchrow($result)) {
        $players[] = $row;
    }
    return $players;
}

// ✅ SECURE - Prepared statement with LIKE
function searchPlayersSecure(\mysqli $db, string $name): array
{
    $stmt = $db->prepare("SELECT * FROM ibl_plr WHERE name LIKE ?");
    $searchTerm = "%" . $name . "%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


// ============================================
// EXAMPLE 3: Dynamic Column Names
// ============================================

// ❌ VULNERABLE - User controls ORDER BY column
function getPlayersOrderedVulnerable($db, string $orderBy): array
{
    $query = "SELECT * FROM ibl_plr ORDER BY $orderBy";
    // Attacker could inject: "name; DROP TABLE ibl_plr; --"
    $result = $db->sql_query($query);
    return [];
}

// ✅ SECURE - Whitelist validation
function getPlayersOrderedSecure(\mysqli $db, string $orderBy): array
{
    $allowedColumns = ['name', 'age', 'position', 'salary', 'rating'];
    
    if (!in_array($orderBy, $allowedColumns, true)) {
        $orderBy = 'name'; // Default to safe column
    }
    
    $query = "SELECT * FROM ibl_plr ORDER BY " . $orderBy;
    $result = $db->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}


// ============================================
// EXAMPLE 4: Legacy Database with Escaping
// ============================================

// ❌ VULNERABLE - No escaping
function findByTeamVulnerable($db, string $teamName): array
{
    $query = "SELECT * FROM ibl_plr WHERE teamname = '$teamName'";
    $result = $db->sql_query($query);
    return [];
}

// ✅ SECURE - Using DatabaseService::escapeString for legacy
function findByTeamSecureLegacy($db, string $teamName): array
{
    $escaped = \Services\DatabaseService::escapeString($db, $teamName);
    $query = "SELECT * FROM ibl_plr WHERE teamname = '$escaped'";
    $result = $db->sql_query($query);
    $players = [];
    while ($row = $db->sql_fetchrow($result)) {
        $players[] = $row;
    }
    return $players;
}


// ============================================
// EXAMPLE 5: Dual Implementation Pattern
// ============================================

// ✅ BEST PRACTICE - Support both database types
function findByTeamDualImplementation($db, string $teamName): array
{
    if (method_exists($db, 'sql_escape_string')) {
        // LEGACY: Use sql_* methods with escaping
        $escaped = \Services\DatabaseService::escapeString($db, $teamName);
        $query = "SELECT * FROM ibl_plr WHERE teamname = '$escaped'";
        $result = $db->sql_query($query);
        $players = [];
        while ($row = $db->sql_fetchrow($result)) {
            $players[] = $row;
        }
        return $players;
    } else {
        // MODERN: Use prepared statements
        $stmt = $db->prepare("SELECT * FROM ibl_plr WHERE teamname = ?");
        $stmt->bind_param("s", $teamName);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}


// ============================================
// EXAMPLE 6: IN Clause with Multiple Values
// ============================================

// ❌ VULNERABLE
function findByIdsVulnerable($db, array $ids): array
{
    $idList = implode(',', $ids);
    $query = "SELECT * FROM ibl_plr WHERE pid IN ($idList)";
    // If $ids contains "1,2); DROP TABLE ibl_plr; --" this is exploitable
    return [];
}

// ✅ SECURE - Validate and cast each ID
function findByIdsSecure(\mysqli $db, array $ids): array
{
    // Ensure all IDs are integers
    $safeIds = array_map('intval', $ids);
    $safeIds = array_filter($safeIds, fn($id) => $id > 0);
    
    if (empty($safeIds)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($safeIds), '?'));
    $types = str_repeat('i', count($safeIds));
    
    $stmt = $db->prepare("SELECT * FROM ibl_plr WHERE pid IN ($placeholders)");
    $stmt->bind_param($types, ...$safeIds);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
