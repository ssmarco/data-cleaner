<?php

namespace Marcz\Cleaner;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Extensions\ScheduledExecutionExtension;

class DataVersionCleaner extends DataObject
{
    private static $db = [
        'Title' => 'Varchar',
        'DataClass' => 'Varchar',
        'DataID' => 'Int',
        'PreviousID' => 'Int',
        'Message' => 'Text',
        'VersionsToKeep' => 'Int',
        'Status' => 'Enum("Queued,Running,Broken,Stopped", "Queued")',
    ];

    private static $table_name = 'DataVersionCleaner';

    private static $defaultRecordClass = 'Page';

    private static $defaultVersionsToKeep = 5;

    private static $defaultInterval = 2; // 2 minutes

    private static $defaultPeriod = 'Minute';

    private static $extensions = [
        ScheduledExecutionExtension::class
    ];

    public function onBeforeWrite()
    {
        if (!$this->DataClass) {
            $this->DataClass = $this->config()->get('defaultRecordClass');
        }

        if (!$this->VersionsToKeep) {
            $this->VersionsToKeep = $this->config()->get('defaultVersionsToKeep');
        }

        if (!$this->DataID) {
            $this->DataID = $this->nextRecordID();
        }

        if (!$this->ExecuteInterval) {
            $this->ExecuteInterval = $this->config()->get('defaultInterval');
        }

        if (!$this->Title) {
            $this->Title = sprintf('ID %d (%s) Version cleanup', $this->DataID, $this->DataClass);
        }

        // Intentionally put below for extensions to read during their own onBeforeWrite
        parent::onBeforeWrite();
    }

    /**
     * Determines the next higher ID to process from the table.
     * @return int
     */
    public function nextRecordID()
    {
        $schema = Injector::inst()->get(DataObjectSchema::class);
        $tableName = $schema->tableName($this->DataClass);
        if (!$schema->classHasTable($this->DataClass)) {
            $tableName = $schema->baseDataTable($this->DataClass);
        }
        $list = DataList::create($this->DataClass);

        $excludeIDs = DataVersionCleaner::get()
            ->filter('DataClass', $this->DataClass)
            ->column('DataID');

        $maxID = $this->DataID;
        if ($excludeIDs) {
            $list = $list->exclude('ID', $excludeIDs);
            $maxID = max($excludeIDs);
        }

        $next = $list
            ->where(['"' . $tableName . '"."ID" > ?' => (int) $maxID])
            ->sort($tableName . '.ID ASC')
            ->limit(1, 0)
            ->first();

        return $next ? $next->ID : 0;
    }

    /**
     * Collection of table names including the parent table
     * e.g. ['"Page_Versions"', '"SiteTree_Versions"']
     * @return array
     */
    public function getTableHierarchy()
    {
        $tables = [];
        $schema = Injector::inst()->get(DataObjectSchema::class);

        $tableName = $schema->tableName($this->DataClass);
        if ($tableName && $schema->classHasTable($this->DataClass)) {
            $tables[$tableName . '_Versions'] = '"' . $tableName . '_Versions"';
        }

        $current = $this->DataClass;
        while ($next = get_parent_class($current)) {
            if ($next === DataObject::class) {
                break;
            }

            $current = $next;
            $nextTable = $schema->tableName($next);
            if ($nextTable && $schema->classHasTable($next)) {
                $tables[$nextTable . '_Versions'] = '"' . $nextTable . '_Versions"';
            }
        }

        return $tables;
    }

    /**
     * Number of records to retain plus latest Draft and Live version
     * @return array
     */
    public function getRetainedVersions()
    {
        $versions = Versioned::get_all_versions($this->DataClass, $this->DataID)
            ->limit($this->VersionsToKeep)
            ->sort('Version DESC')
            ->column('Version');

        $liveVersion = Versioned::get_versionnumber_by_stage($this->DataClass, Versioned::LIVE, $this->DataID);
        if ($liveVersion) {
            $versions[] = $liveVersion;
        }

        $draftVersion = Versioned::get_versionnumber_by_stage($this->DataClass, Versioned::DRAFT, $this->DataID);
        if ($draftVersion) {
            $versions[] = $draftVersion;
        }

        return array_unique($versions);
    }

    /**
     * Process database table cleanup
     * @return void
     * @throws \Exception
     */
    public function processCleanup()
    {
        $retainedVersions = $this->getRetainedVersions();
        if (!$retainedVersions) {
            throw new \Exception('No versions found for RecordID: ' . $this->DataID);
        }

        $tables = $this->getTableHierarchy();
        foreach ($tables as $table) {
            $delete = SQLDelete::create(
                $table,
                [
                    '"RecordID"' => $this->DataID,
                    '"Version" NOT IN (' . DB::placeholders($retainedVersions) . ')' => $retainedVersions,
                ]
            );
            $delete->execute();
        }
    }

    /**
     * Stops the rescheduling of queue job
     * @param string $status
     * @param string $message
     *
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function stopSchedule($status = 'Stopped', $message = '')
    {
        $this->FirstExecution = ''; // onBeforeWrite checks of ScheduledExecutionExtension
        $this->ExecuteEvery = '';   // rescheduling logic of ScheduledExecutionJob
        $this->Status = $status;
        $this->Message = $message;
        $this->write();
        return $this;
    }

    /**
     * Interface with ScheduledExecutionExtension and executed by ScheduledExecutionJob
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function onScheduledExecution()
    {
        ini_set('max_execution_time', 0);

        try {
            $this->Status = 'Running';
            $this->FirstExecution = ''; //prevents duplicate job creation
            $this->write();
            $this->processCleanup();
        } catch (\Exception $exception) {
            return $this->stopSchedule('Broken', $exception->getMessage());
        }

        $nextID = $this->nextRecordID();
        $this->PreviousID = $this->DataID;
        if ($nextID) {
            $this->Status = 'Queued';
            $this->DataID = $nextID;
            $this->Title = sprintf('ID %d (%s) Version cleanup', $this->DataID, $this->DataClass);
            $this->write();
            return $this;
        }

        return $this->stopSchedule('Stopped', 'No more records to process');
    }
}
