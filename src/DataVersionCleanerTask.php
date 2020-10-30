<?php

namespace Marcz\Cleaner;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\FieldType\DBDatetime;

class DataVersionCleanerTask extends BuildTask
{
    /** @var string DataObject child class */
    /**
     * @var string child class of DataObject
     *             eg. 'SilverStripe\Assets\Image'
     *                 'SilverStripe\Assets\File',
     *                 'SilverStripe\Blog\Model\BlogPost',
     */
    private static $RecordClass = 'SilverStripe\CMS\Model\VirtualPage';
    private static $VersionsToKeep = 5;

    public function run($request)
    {
        $cleaner = Injector::inst()->get(DataVersionCleaner::class);
        $cleaner->DataClass = $request->getVar('RecordClass') ?: $this->config()->get('RecordClass');
        $nextID = $cleaner->nextRecordID();
        if ($nextID) {
            $cleaner->DataID = $nextID;
            $cleaner->VersionsToKeep = $this->config()->get('VersionsToKeep');
            $cleaner->write();

            Debug::dump(sprintf(
                'Found Record ID %d (%s)',
                $nextID,
                $cleaner->DataClass
            ));

            // Second write needs ID to trigger processing of queue job
            $cleaner->FirstExecution = DBDatetime::now()->Rfc2822();
            $cleaner->ExecuteEvery = $cleaner->config()->get('defaultPeriod');
            $cleaner->write();

            return;
        }

        Debug::dump('No records found.');
    }
}

