<?php

declare(strict_types=1);

namespace Player;

use Player\Contract\PlayerContractCalculator;
use Player\Contract\PlayerContractValidator;
use Player\Contracts\PlayerInterface;
use Season\Season;

/**
 * Player - Facade for player-related operations
 *
 * This class provides a unified interface for accessing player data and
 * delegating to specialized calculator and validator classes for business logic.
 *
 * Rating, identity and contract field getters live in the PlayerRatingsGetters, PlayerIdentityGetters and PlayerContractGetters traits (ADR-0119).
 *
 * @see PlayerInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type HistoricalPlayerRow from \Player\Contracts\PlayerRepositoryInterface
 */
class Player implements PlayerInterface
{
    use PlayerIdentityGetters, PlayerContractGetters, PlayerRatingsGetters;

    /** @var \mysqli Database connection */
    protected \mysqli $db;

    /** @var PlayerData|null Player data object */
    protected ?PlayerData $playerData = null;

    /** @var PlayerRepository Player repository for database operations */
    protected PlayerRepository $repository;

    /** @var PlayerContractCalculator Contract calculation helper */
    protected PlayerContractCalculator $contractCalculator;

    /** @var PlayerContractValidator Contract validation helper */
    protected PlayerContractValidator $contractValidator;

    /** @var PlayerNameDecorator Name decoration helper */
    protected PlayerNameDecorator $nameDecorator;

    /** @var PlayerInjuryCalculator Injury calculation helper */
    protected PlayerInjuryCalculator $injuryCalculator;

    /**
     * Create a new Player instance
     */
    public function __construct()
    {
        $this->contractCalculator = new PlayerContractCalculator();
        $this->contractValidator = new PlayerContractValidator();
        $this->nameDecorator = new PlayerNameDecorator();
        $this->injuryCalculator = new PlayerInjuryCalculator();
    }

    /**
     * Create a Player instance from a player ID
     *
     * @param \mysqli $db Database connection
     * @param int $playerID Player unique identifier
     * @return self Populated Player instance
     */
    public static function withPlayerID(\mysqli $db, int $playerID): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->loadByID($playerID);
        return $instance;
    }

    /**
     * Create a Player instance from a player row array
     *
     * @param \mysqli $db Database connection
     * @param PlayerRow $plrRow Player row data from database
     * @return self Populated Player instance
     */
    public static function withPlrRow(\mysqli $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromCurrentRow($plrRow);
        return $instance;
    }

    /**
     * Create a Player instance from a historical player row
     *
     * @param \mysqli $db Database connection
     * @param HistoricalPlayerRow $plrRow Historical player row data
     * @return self Populated Player instance
     */
    public static function withHistoricalPlrRow(\mysqli $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromHistoricalRow($plrRow);
        return $instance;
    }

    /**
     * Initialize the Player instance with database and repository
     *
     * @param \mysqli $db Database connection
     */
    protected function initialize(\mysqli $db): void
    {
        $this->db = $db;
        $this->repository = new PlayerRepository($db);
    }

    /**
     * Get the PlayerData object, throwing if not initialized
     *
     * @throws \RuntimeException If player data has not been loaded
     */
    private function getPlayerData(): PlayerData
    {
        if ($this->playerData === null) {
            throw new \RuntimeException('Player data has not been loaded');
        }
        return $this->playerData;
    }

    // --- Collaborator-backed methods; pure field getters live in the three getter traits ---

    /** @see PlayerInterface::getDecoratedName() */
    public function getDecoratedName(): ?string
    {
        return $this->playerData !== null ? $this->decoratePlayerName() : null;
    }

    /** @see PlayerInterface::getNameStatusClass() */
    public function getNameStatusClass(): string
    {
        return $this->playerData !== null
            ? $this->nameDecorator->getNameStatusClass($this->getPlayerData())
            : '';
    }

    /**
     * @see PlayerInterface::decoratePlayerName()
     */
    public function decoratePlayerName(): string
    {
        return $this->nameDecorator->decoratePlayerName($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getCurrentSeasonSalary()
     */
    public function getCurrentSeasonSalary(): int
    {
        return $this->contractCalculator->getCurrentSeasonSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getFreeAgencyDemands()
     *
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
     */
    public function getFreeAgencyDemands(): array
    {
        return $this->repository->getFreeAgencyDemands($this->getPlayerID() ?? 0);
    }

    /**
     * @see PlayerInterface::getInjuryReturnDate()
     */
    public function getInjuryReturnDate(string $rawLastSimEndDate): string
    {
        return $this->injuryCalculator->getInjuryReturnDate($this->getPlayerData(), $rawLastSimEndDate);
    }

    /**
     * @see PlayerInterface::getNextSeasonSalary()
     */
    public function getNextSeasonSalary(): int
    {
        return $this->contractCalculator->getNextSeasonSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getLongBuyoutArray()
     */
    public function getLongBuyoutArray(): array
    {
        return $this->contractCalculator->getLongBuyoutArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getShortBuyoutArray()
     */
    public function getShortBuyoutArray(): array
    {
        return $this->contractCalculator->getShortBuyoutArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getRemainingContractArray()
     *
     * @return array<int, int>
     */
    public function getRemainingContractArray(): array
    {
        return $this->contractCalculator->getRemainingContractArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getTotalRemainingSalary()
     */
    public function getTotalRemainingSalary(): int
    {
        return $this->contractCalculator->getTotalRemainingSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getFutureSalaries()
     */
    public function getFutureSalaries(): array
    {
        return $this->contractCalculator->getFutureSalaries($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::canRenegotiateContract()
     */
    public function canRenegotiateContract(?Season $season = null): bool
    {
        return $this->contractValidator->canRenegotiateContract($this->getPlayerData(), $season);
    }

    /**
     * @see PlayerInterface::canRookieOption()
     */
    public function canRookieOption(string $seasonPhase): bool
    {
        return $this->contractValidator->canRookieOption($this->getPlayerData(), $seasonPhase);
    }

    /**
     * @see PlayerInterface::getFinalYearRookieContractSalary()
     */
    public function getFinalYearRookieContractSalary(): int
    {
        return $this->contractValidator->getFinalYearRookieContractSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::isPlayerFreeAgent()
     */
    public function isPlayerFreeAgent(Season $season): bool
    {
        return $this->contractValidator->isPlayerFreeAgent($this->getPlayerData(), $season);
    }

    /**
     * @see PlayerInterface::isSalaryPlaceholder()
     */
    public function isSalaryPlaceholder(): bool
    {
        // Cash and buyout entries are no longer stored in ibl_plr.
        // They live in ibl_cash_considerations. No player loaded from
        // ibl_plr can be a salary placeholder.
        return false;
    }

    /**
     * @see PlayerInterface::wasRookieOptioned()
     */
    public function wasRookieOptioned(): bool
    {
        return $this->contractValidator->wasRookieOptioned($this->getPlayerData());
    }
}
