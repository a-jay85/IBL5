<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Contracts\JsbImportServiceInterface;
use JsbParser\Importers\AswImporter;
use JsbParser\Importers\AwaImporter;
use JsbParser\Importers\CarImporter;
use JsbParser\Importers\DraImporter;
use JsbParser\Importers\HisImporter;
use JsbParser\Importers\HofImporter;
use JsbParser\Importers\PlbImporter;
use JsbParser\Importers\RcbImporter;
use JsbParser\Importers\RetImporter;
use JsbParser\Importers\TrnImporter;
use PlrParser\PlrOrdinalMap;

class JsbImportService implements JsbImportServiceInterface
{
    private CarImporter $car;
    private TrnImporter $trn;
    private HisImporter $his;
    private AswImporter $asw;
    private AwaImporter $awa;
    private RcbImporter $rcb;
    private PlbImporter $plb;
    private DraImporter $dra;
    private RetImporter $ret;
    private HofImporter $hof;

    public function __construct(JsbImportRepositoryInterface $repository, PlayerIdResolver $resolver)
    {
        $this->car = new CarImporter($repository, $resolver);
        $this->trn = new TrnImporter($repository);
        $this->his = new HisImporter($repository);
        $this->asw = new AswImporter($repository);
        $this->awa = new AwaImporter($repository);
        $this->rcb = new RcbImporter($repository);
        $this->plb = new PlbImporter($repository);
        $this->dra = new DraImporter($repository);
        $this->ret = new RetImporter($repository);
        $this->hof = new HofImporter($repository);
    }

    /** @see JsbImportServiceInterface::processCarData() */
    public function processCarData(string $data, ?int $filterYear = null): JsbImportResult
    {
        return $this->car->import($data, $filterYear);
    }

    /** @see JsbImportServiceInterface::processCarFile() */
    public function processCarFile(string $filePath, ?int $filterYear = null): JsbImportResult
    {
        return $this->car->importFile($filePath, $filterYear);
    }

    /** @see JsbImportServiceInterface::processTrnData() */
    public function processTrnData(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->trn->import($data, $sourceLabel);
    }

    /** @see JsbImportServiceInterface::processTrnFile() */
    public function processTrnFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->trn->importFile($filePath, $sourceLabel);
    }

    /** @see JsbImportServiceInterface::processHisData() */
    public function processHisData(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->his->import($data, $sourceLabel);
    }

    /** @see JsbImportServiceInterface::processHisFile() */
    public function processHisFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->his->importFile($filePath, $sourceLabel);
    }

    /** @see JsbImportServiceInterface::processAswData() */
    public function processAswData(string $data, int $seasonYear): JsbImportResult
    {
        return $this->asw->import($data, $seasonYear);
    }

    /** @see JsbImportServiceInterface::processAswFile() */
    public function processAswFile(string $filePath, int $seasonYear): JsbImportResult
    {
        return $this->asw->importFile($filePath, $seasonYear);
    }

    /** @see JsbImportServiceInterface::processAwaData() */
    public function processAwaData(string $awaData, string $carData, ?int $filterYear = null): JsbImportResult
    {
        return $this->awa->import($awaData, $carData, $filterYear);
    }

    /** @see JsbImportServiceInterface::processAwaFile() */
    public function processAwaFile(string $awaPath, string $carPath, ?int $filterYear = null): JsbImportResult
    {
        return $this->awa->importFiles($awaPath, $carPath, $filterYear);
    }

    /** @see JsbImportServiceInterface::processRcbData() */
    public function processRcbData(string $data, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        return $this->rcb->import($data, $seasonYear, $sourceLabel, $includeAlltime);
    }

    /** @see JsbImportServiceInterface::processRcbFile() */
    public function processRcbFile(string $filePath, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        return $this->rcb->importFile($filePath, $seasonYear, $sourceLabel, $includeAlltime);
    }

    /** @see JsbImportServiceInterface::processPlbData() */
    public function processPlbData(
        string $data,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        return $this->plb->import($data, $map, $seasonYear, $simNumber, $sourceArchive);
    }

    /** @see JsbImportServiceInterface::processPlbFile() */
    public function processPlbFile(
        string $filePath,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        return $this->plb->importFile($filePath, $map, $seasonYear, $simNumber, $sourceArchive);
    }

    /** @see JsbImportServiceInterface::processDraData() */
    public function processDraData(string $data): JsbImportResult
    {
        return $this->dra->import($data);
    }

    /** @see JsbImportServiceInterface::processDraFile() */
    public function processDraFile(string $filePath): JsbImportResult
    {
        return $this->dra->importFile($filePath);
    }

    /** @see JsbImportServiceInterface::processRetData() */
    public function processRetData(string $data, int $retirementYear): JsbImportResult
    {
        return $this->ret->import($data, $retirementYear);
    }

    /** @see JsbImportServiceInterface::processRetFile() */
    public function processRetFile(string $filePath, int $retirementYear): JsbImportResult
    {
        return $this->ret->importFile($filePath, $retirementYear);
    }

    /** @see JsbImportServiceInterface::processHofData() */
    public function processHofData(string $data): JsbImportResult
    {
        return $this->hof->import($data);
    }

    /** @see JsbImportServiceInterface::processHofFile() */
    public function processHofFile(string $filePath): JsbImportResult
    {
        return $this->hof->importFile($filePath);
    }
}
