<?php

declare(strict_types=1);

namespace PlayerSearch;

/**
 * PlayerSearchService - Business logic for player search
 * 
 * Handles data transformation and processing for search results.
 */
class PlayerSearchService
{
    private PlayerSearchValidator $validator;
    private PlayerSearchRepository $repository;

    /**
     * Constructor
     * 
     * @param PlayerSearchValidator $validator Validator instance
     * @param PlayerSearchRepository $repository Repository instance
     */
    public function __construct(
        PlayerSearchValidator $validator,
        PlayerSearchRepository $repository
    ) {
        $this->validator = $validator;
        $this->repository = $repository;
    }

    /**
     * Execute a player search based on form parameters
     * 
     * @param array<string, mixed> $rawParams Raw POST parameters
     * @return array{players: array<array<string, mixed>>, count: int, params: array<string, mixed>}
     */
    public function search(array $rawParams): array
    {
        // Validate parameters
        $params = $this->validator->validateSearchParams($rawParams);

        // Check if form was submitted
        if (!$this->validator->isFormSubmitted($params)) {
            return [
                'players' => [],
                'count' => 0,
                'params' => $params
            ];
        }

        // Execute search
        $searchResult = $this->repository->searchPlayers($params);

        return [
            'players' => $searchResult['results'],
            'count' => $searchResult['count'],
            'params' => $params
        ];
    }

    /**
     * Process a player row for display
     * 
     * @param array<string, mixed> $player Raw player data from database
     * @return array<string, mixed> Processed player data ready for display
     */
    public function processPlayerForDisplay(array $player): array
    {
        return [
            // Identification
            'pid' => (int)($player['pid'] ?? 0),
            'name' => (string)($player['name'] ?? ''),
            'pos' => (string)($player['pos'] ?? ''),
            'tid' => (int)($player['tid'] ?? 0),
            'teamname' => (string)($player['teamname'] ?? ''),
            'retired' => (int)($player['retired'] ?? 0),
            
            // Basic attributes
            'age' => (int)($player['age'] ?? 0),
            'sta' => (int)($player['sta'] ?? 0),
            'college' => (string)($player['college'] ?? ''),
            'exp' => (int)($player['exp'] ?? 0),
            'bird' => (int)($player['bird'] ?? 0),
            
            // Ratings
            'r_fga' => (int)($player['r_fga'] ?? 0),
            'r_fgp' => (int)($player['r_fgp'] ?? 0),
            'r_fta' => (int)($player['r_fta'] ?? 0),
            'r_ftp' => (int)($player['r_ftp'] ?? 0),
            'r_tga' => (int)($player['r_tga'] ?? 0),
            'r_tgp' => (int)($player['r_tgp'] ?? 0),
            'r_orb' => (int)($player['r_orb'] ?? 0),
            'r_drb' => (int)($player['r_drb'] ?? 0),
            'r_ast' => (int)($player['r_ast'] ?? 0),
            'r_stl' => (int)($player['r_stl'] ?? 0),
            'r_tvr' => (int)($player['r_to'] ?? 0), // Note: DB column is r_to but displays as tvr
            'r_blk' => (int)($player['r_blk'] ?? 0),
            'r_foul' => (int)($player['r_foul'] ?? 0),
            
            // Skill ratings
            'oo' => (int)($player['oo'] ?? 0),
            'do' => (int)($player['do'] ?? 0),
            'po' => (int)($player['po'] ?? 0),
            'to' => (int)($player['to'] ?? 0),
            'od' => (int)($player['od'] ?? 0),
            'dd' => (int)($player['dd'] ?? 0),
            'pd' => (int)($player['pd'] ?? 0),
            'td' => (int)($player['td'] ?? 0),
            
            // Meta attributes
            'talent' => (int)($player['talent'] ?? 0),
            'skill' => (int)($player['skill'] ?? 0),
            'intangibles' => (int)($player['intangibles'] ?? 0),
            'Clutch' => (int)($player['Clutch'] ?? 0),
            'Consistency' => (int)($player['Consistency'] ?? 0),
        ];
    }

}
