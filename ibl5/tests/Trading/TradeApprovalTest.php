<?php

use PHPUnit\Framework\TestCase;

class TradeApprovalTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        
        // Set up mock data for team ID lookups
        $this->db->setMockData([
            ['counter' => 1000]
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
        // Team A sends Player 1
        // Team B sends cash
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 1,      // 1 item from offering team
            'fieldsCounter' => 1,      // Total 1 item (no items from listening team in this simplified test)
            'userSendsCash' => [0, 0, 0, 0, 0, 0, 0],
            'partnerSendsCash' => [0, 100, 200, 0, 0, 0, 0], // Team B sends cash
            'check' => ['on'],         // Offering team item is checked
            'contract' => [1000],      // Player salary
            'index' => [123],          // Player ID
            'type' => [1]              // 1 = player
        ];
        
        $result = $tradeOffer->createTradeOffer($tradeData);
        
        $this->assertTrue($result['success'], 'Trade offer should be created successfully');
        
        // Get all executed queries
        $queries = $this->db->getExecutedQueries();
        
        // Filter to INSERT INTO ibl_trade_info queries
        $tradeInfoInserts = array_filter($queries, function($query) {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });
        
        // Check each trade info insert
        foreach ($tradeInfoInserts as $query) {
            // Extract the approval field value
            // The query format is: INSERT INTO ibl_trade_info (...) VALUES (...)
            // We need to check that approval is always 'Boston Celtics' (the listening team)
            
            // For player items from offering team (Atlanta sends to Boston)
            if (strpos($query, "'Atlanta Hawks'") !== false && strpos($query, "'Boston Celtics'") !== false) {
                // This is an item from Atlanta to Boston
                // Check that approval is Boston Celtics
                // Pattern matches: VALUES ('tradeid', 'itemid', 'type', 'from', 'to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];
                    $this->assertEquals('Boston Celtics', $approval, 
                        "For items from {$from} to {$to}, approval should be Boston Celtics");
                }
            }
            
            // For cash items from listening team (Boston sends to Atlanta)
            if (strpos($query, "'cash'") !== false) {
                // This is a cash item
                // Extract the from and to teams and approval
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
        
        // Team A offers Player 1 to Team B
        // Team B sends cash back to Team A
        // Expected: approval should be Team B (listening team) for ALL items
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 1,
            'fieldsCounter' => 1,
            'userSendsCash' => [0, 0, 0, 0, 0, 0, 0],       // Atlanta sends no cash
            'partnerSendsCash' => [0, 500, 500, 0, 0, 0, 0], // Boston sends cash
            'check' => ['on'],
            'contract' => [2000],
            'index' => [456],
            'type' => [1]
        ];
        
        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success']);
        
        $queries = $this->db->getExecutedQueries();
        
        // Find the cash insert from Boston to Atlanta
        foreach ($queries as $query) {
            if (strpos($query, "'cash'") !== false && 
                strpos($query, "'Boston Celtics'") !== false && 
                strpos($query, "'Atlanta Hawks'") !== false) {
                
                // Extract approval value
                // Pattern matches: VALUES ('tradeid', 'itemid', 'cash', 'from', 'to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'cash'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];
                    
                    // This test verifies the fix: approval should always be the listening team (Boston Celtics)
                    $this->assertEquals('Boston Celtics', $approval, 
                        "When Boston (listening) sends cash to Atlanta (offering), " .
                        "approval must be Boston Celtics (the listening team). Got: {$approval}");
                }
            }
        }
    }
}
