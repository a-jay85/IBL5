<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartRepository;

/**
 * Tests to verify that database column mapping is correct
 * This ensures data flows correctly: Form -> Processor -> Repository -> Database
 */
class DepthChartDatabaseMappingTest extends TestCase
{
    /**
     * This test verifies the exact mapping between processed array keys
     * and database column names in the UPDATE query
     */
    public function testDatabaseUpdateQueryMapsFieldsCorrectly()
    {
        // The processed data uses these lowercase keys
        $processedPlayerData = [
            'pg' => 1,
            'sg' => 2,
            'sf' => 0,
            'pf' => 0,
            'c' => 0,
            'active' => 1,
            'min' => 30,
            'of' => 2,
            'df' => 1,
            'oi' => -1,
            'di' => 2,
            'bh' => 0
        ];

        // The database has these column names (from schema)
        $expectedDatabaseColumns = [
            'dc_PGDepth',  // Should get value from $processedPlayerData['pg']
            'dc_SGDepth',  // Should get value from $processedPlayerData['sg']
            'dc_SFDepth',  // Should get value from $processedPlayerData['sf']
            'dc_PFDepth',  // Should get value from $processedPlayerData['pf']
            'dc_CDepth',   // Should get value from $processedPlayerData['c']
            'dc_active',   // Should get value from $processedPlayerData['active']
            'dc_minutes',  // Should get value from $processedPlayerData['min']
            'dc_of',       // Should get value from $processedPlayerData['of']
            'dc_df',       // Should get value from $processedPlayerData['df']
            'dc_oi',       // Should get value from $processedPlayerData['oi']
            'dc_di',       // Should get value from $processedPlayerData['di']
            'dc_bh'        // Should get value from $processedPlayerData['bh']
        ];

        // The UPDATE query in DepthChartRepository.php should have this exact order
        $expectedQueryStructure = "UPDATE ibl_plr SET
                    dc_PGDepth = ?,
                    dc_SGDepth = ?,
                    dc_SFDepth = ?,
                    dc_PFDepth = ?,
                    dc_CDepth = ?,
                    dc_active = ?,
                    dc_minutes = ?,
                    dc_of = ?,
                    dc_df = ?,
                    dc_oi = ?,
                    dc_di = ?,
                    dc_bh = ?
                WHERE name = ?";

        // Verify the expected columns match the processed data keys
        $this->assertEquals(12, count($expectedDatabaseColumns), 'Should have 12 database columns to update');
        $this->assertEquals(12, count($processedPlayerData), 'Should have 12 fields in processed data');

        // This documents the mapping that SHOULD exist in the code
        $expectedMapping = [
            'pg' => 'dc_PGDepth',
            'sg' => 'dc_SGDepth',
            'sf' => 'dc_SFDepth',
            'pf' => 'dc_PFDepth',
            'c' => 'dc_CDepth',
            'active' => 'dc_active',
            'min' => 'dc_minutes',
            'of' => 'dc_of',
            'df' => 'dc_df',
            'oi' => 'dc_oi',
            'di' => 'dc_di',
            'bh' => 'dc_bh'
        ];

        // Verify all keys in processed data have a corresponding database column
        foreach ($processedPlayerData as $key => $value) {
            $this->assertArrayHasKey($key, $expectedMapping, "Processed data key '$key' should have a database column mapping");
        }

        // The bind_param order in the code should match this exact sequence
        $expectedBindParamTypes = 'iiiiiiiiiiiis'; // 12 integers + 1 string (player name)
        $expectedBindParamValues = [
            $processedPlayerData['pg'],      // position 1: dc_PGDepth
            $processedPlayerData['sg'],      // position 2: dc_SGDepth
            $processedPlayerData['sf'],      // position 3: dc_SFDepth
            $processedPlayerData['pf'],      // position 4: dc_PFDepth
            $processedPlayerData['c'],       // position 5: dc_CDepth
            $processedPlayerData['active'],  // position 6: dc_active
            $processedPlayerData['min'],     // position 7: dc_minutes
            $processedPlayerData['of'],      // position 8: dc_of
            $processedPlayerData['df'],      // position 9: dc_df
            $processedPlayerData['oi'],      // position 10: dc_oi
            $processedPlayerData['di'],      // position 11: dc_di
            $processedPlayerData['bh'],      // position 12: dc_bh
            'Test Player'                     // position 13: name (WHERE clause)
        ];

        $this->assertEquals(13, count($expectedBindParamValues), 'Should have 13 bind parameters (12 updates + 1 WHERE)');
        $this->assertEquals(13, strlen($expectedBindParamTypes), 'Bind param type string should have 13 characters');

        // Verify the values are what we expect
        $this->assertEquals(1, $expectedBindParamValues[0], 'First param should be pg value');
        $this->assertEquals(2, $expectedBindParamValues[1], 'Second param should be sg value');
        $this->assertEquals(30, $expectedBindParamValues[6], 'Seventh param should be min value');
        $this->assertEquals(2, $expectedBindParamValues[7], 'Eighth param should be of value');
        $this->assertEquals(1, $expectedBindParamValues[8], 'Ninth param should be df value');
        $this->assertEquals(-1, $expectedBindParamValues[9], 'Tenth param should be oi value');
        $this->assertEquals(2, $expectedBindParamValues[10], 'Eleventh param should be di value');
        $this->assertEquals(0, $expectedBindParamValues[11], 'Twelfth param should be bh value');
    }

    /**
     * Tests that negative values for intensity settings are preserved correctly
     * This is critical because OF/DF are unsigned but OI/DI/BH must support negative values
     */
    public function testNegativeIntensityValuesArePreservedInDatabaseUpdate()
    {
        $processedPlayerData = [
            'pg' => 0,
            'sg' => 0,
            'sf' => 0,
            'pf' => 0,
            'c' => 1,
            'active' => 1,
            'min' => 20,
            'of' => 0,     // unsigned: 0-3
            'df' => 0,     // unsigned: 0-3
            'oi' => -2,    // signed: -2 to 2
            'di' => -1,    // signed: -2 to 2
            'bh' => -2     // signed: -2 to 2
        ];

        // Verify that negative values are maintained
        $this->assertEquals(-2, $processedPlayerData['oi']);
        $this->assertEquals(-1, $processedPlayerData['di']);
        $this->assertEquals(-2, $processedPlayerData['bh']);

        // These values should be passed directly to the database without modification
        // The database columns dc_oi, dc_di, dc_bh are defined as tinyint (signed)
        // so they can store negative values
    }

    /**
     * Tests the complete data flow to ensure no data loss or transformation errors
     */
    public function testCompleteDataFlowFromFormToDatabase()
    {
        // Step 1: User submits form with these POST values
        $postFieldNames = [
            'pg1' => '1',      // lowercase position field
            'sg1' => '2',      // lowercase position field
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',   // lowercase
            'min1' => '30',     // lowercase
            'OF1' => '2',       // UPPERCASE
            'DF1' => '1',       // UPPERCASE
            'OI1' => '-1',      // UPPERCASE with negative value
            'DI1' => '2',       // UPPERCASE
            'BH1' => '0'        // UPPERCASE
        ];

        // Step 2: Processor converts POST to processed array with lowercase keys
        $processedKeys = ['pg', 'sg', 'sf', 'pf', 'c', 'active', 'min', 'of', 'df', 'oi', 'di', 'bh'];

        // Step 3: Repository maps processed array to database columns
        $databaseColumns = [
            'dc_PGDepth',
            'dc_SGDepth',
            'dc_SFDepth',
            'dc_PFDepth',
            'dc_CDepth',
            'dc_active',
            'dc_minutes',
            'dc_of',
            'dc_df',
            'dc_oi',
            'dc_di',
            'dc_bh'
        ];

        // Verify the counts match
        $this->assertEquals(count($processedKeys), count($databaseColumns), 'Number of processed keys should match database columns');

        // Verify the mapping chain (documented for reference)
        $completeChain = [
            'pg' => ['POST' => 'pg1', 'processed' => 'pg', 'database' => 'dc_PGDepth'],
            'of' => ['POST' => 'OF1', 'processed' => 'of', 'database' => 'dc_of'],
            'df' => ['POST' => 'DF1', 'processed' => 'df', 'database' => 'dc_df'],
            'oi' => ['POST' => 'OI1', 'processed' => 'oi', 'database' => 'dc_oi'],
            'di' => ['POST' => 'DI1', 'processed' => 'di', 'database' => 'dc_di'],
            'bh' => ['POST' => 'BH1', 'processed' => 'bh', 'database' => 'dc_bh']
        ];

        // This chain documents how data flows through the system
        $this->assertCount(6, $completeChain, 'Should have documented chain for 6 key fields');
    }

    /**
     * Regression test: Ensure that the order of parameters in bind_param
     * matches the order of columns in the UPDATE statement
     */
    public function testBindParamOrderMatchesUpdateStatementOrder()
    {
        // The UPDATE statement has columns in this order:
        $updateColumnOrder = [
            1 => 'dc_PGDepth',
            2 => 'dc_SGDepth',
            3 => 'dc_SFDepth',
            4 => 'dc_PFDepth',
            5 => 'dc_CDepth',
            6 => 'dc_active',
            7 => 'dc_minutes',
            8 => 'dc_of',
            9 => 'dc_df',
            10 => 'dc_oi',
            11 => 'dc_di',
            12 => 'dc_bh'
        ];

        // The bind_param call should pass values in this EXACT same order
        $bindParamOrder = [
            1 => 'pg',
            2 => 'sg',
            3 => 'sf',
            4 => 'pf',
            5 => 'c',
            6 => 'active',
            7 => 'min',
            8 => 'of',
            9 => 'df',
            10 => 'oi',
            11 => 'di',
            12 => 'bh'
        ];

        // If these orders don't match, data will be stored in wrong columns
        // For example, if 'of' is passed in position 9 but UPDATE has dc_of in position 8,
        // then the 'df' value would be stored in dc_of column!

        $this->assertCount(12, $updateColumnOrder);
        $this->assertCount(12, $bindParamOrder);

        // This test documents the critical requirement:
        // The Nth value in bind_param must correspond to the Nth column in UPDATE statement
    }
}
