<?php

use PHPUnit\Framework\TestCase;

class TradeApprovalTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        
        // Set up mock data for team ID lookups and player data
        $this->db->setMockData([
            ['counter' => 1000],
            ['name' => 'Test Player', 'pos' => 'PG']
        ]);
    }
    
    /**
     * Test that approval field is always set to the listening team (receiving team)
     * This test verifies the fix for the bug where the offering team could accept
     * their own trade offer when cash was involved.
     */
    public function testApprovalAlwaysSetToListeningTeam()
    {
        $tradeOffer = new Trading_TradeOffer($this->db);
        
        // Prepare trade data: Team A offers to Team B
        // Team A sends cash only (no players or picks)
        // Team B sends cash back
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 0,      // No items from offering team (only cash)
            'fieldsCounter' => 0,      // No items from listening team (only cash)
            'userSendsCash' => [0, 150, 150, 0, 0, 0, 0],    // Team A sends cash
            'partnerSendsCash' => [0, 100, 200, 0, 0, 0, 0], // Team B sends cash
            'check' => [],             // No items to check
            'contract' => [],          // No contracts
            'index' => [],             // No items
            'type' => []               // No item types
        ];
        
        $result = $tradeOffer->createTradeOffer($tradeData);
        
        $this->assertTrue($result['success'], 'Trade offer should be created successfully');
        
        // Get all executed queries
        $queries = $this->db->getExecutedQueries();
        
        // Filter to INSERT INTO ibl_trade_info queries
        $tradeInfoInserts = array_filter($queries, function($query) {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });
        
        // Check each trade info insert - all should be cash items with approval = Boston Celtics
        foreach ($tradeInfoInserts as $query) {
            // For cash items, extract the from, to, and approval teams
            if (strpos($query, "'cash'") !== false) {
                // Pattern matches: VALUES ('tradeid', 'itemid', 'cash', 'from', 'to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'cash'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];
                    
                    // Approval should ALWAYS be 'Boston Celtics' (the listening team), 
                    // regardless of whether the cash is from Atlanta or Boston
                    $this->assertEquals('Boston Celtics', $approval, 
                        "For cash from {$from} to {$to}, approval should always be the listening team (Boston Celtics), but got {$approval}");
                }
            }
        }
    }
    
    /**
     * Test the specific bug scenario: when listening team sends cash back to offering team,
     * the approval should still be the listening team, not the offering team
     */
    public function testCashFromListeningTeamHasCorrectApproval()
    {
        $tradeOffer = new Trading_TradeOffer($this->db);
        
        // Team A offers cash to Team B
        // Team B sends cash back to Team A
        // Expected: approval should be Team B (listening team) for ALL cash items
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 0,
            'fieldsCounter' => 0,
            'userSendsCash' => [0, 300, 200, 0, 0, 0, 0],       // Atlanta sends cash
            'partnerSendsCash' => [0, 500, 500, 0, 0, 0, 0],    // Boston sends cash back
            'check' => [],
            'contract' => [],
            'index' => [],
            'type' => []
        ];
        
        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success']);
        
        $queries = $this->db->getExecutedQueries();
        
        // Find all cash inserts and verify approval is always Boston Celtics
        foreach ($queries as $query) {
            if (strpos($query, "'cash'") !== false) {
                // Extract approval value
                // Pattern matches: VALUES ('tradeid', 'itemid', 'cash', 'from', 'to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'cash'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];
                    
                    // This test verifies the fix: approval should always be the listening team (Boston Celtics)
                    $this->assertEquals('Boston Celtics', $approval, 
                        "When {$from} sends cash to {$to}, " .
                        "approval must be Boston Celtics (the listening team). Got: {$approval}");
                }
            }
        }
    }
}
