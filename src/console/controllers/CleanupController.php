<?php

namespace hybridinteractive\contactformextensions\console\controllers;

use Craft;
use craft\console\Controller;
use hybridinteractive\contactformextensions\ContactFormExtensions;
use hybridinteractive\contactformextensions\elements\Submission;
use yii\console\ExitCode;

class CleanupController extends Controller
{
    public $defaultAction = 'soft-deleted';

    /**
     * Hard-delete all soft-deleted submissions from the database.
     *
     * @return int
     */
    public function actionSoftDeleted(): int
    {
        $this->stdout("=== Contact Form Extensions - Cleanup Soft-Deleted ===\n");
        
        // Find all trashed submissions
        $query = Submission::find()
            ->trashed()
            ->status(null)
            ->limit(null);

        $count = $query->count();
        
        if ($count === 0) {
            $this->stdout("\n✓ No soft-deleted submissions found.\n");
            return ExitCode::OK;
        }

        $this->stdout("\nFound {$count} soft-deleted submissions.\n");
        $this->stdout("Hard-deleting them permanently...\n");

        $elementsService = Craft::$app->getElements();
        $i = 0;
        $deleteCount = 0;
        $batchSize = 100;
        
        // Process in batches
        for ($offset = 0; $offset < $count; $offset += $batchSize) {
            $submissions = Submission::find()
                ->trashed()
                ->status(null)
                ->limit($batchSize)
                ->offset($offset)
                ->all();
            
            foreach ($submissions as $submission) {
                // Hard delete = permanently remove from database
                $elementsService->deleteElement($submission, true);
                $deleteCount = ++$i;
                
                if ($i % 1000 === 0) {
                    $this->stdout("  Deleted {$i} / {$count}...\n");
                }
            }
        }

        $this->stdout("\n✓ Successfully hard-deleted {$deleteCount} soft-deleted submissions.\n");
        
        return ExitCode::OK;
    }

    /**
     * Show database statistics for form submissions.
     *
     * @return int
     */
    public function actionStats(): int
    {
        $this->stdout("=== Contact Form Extensions - Database Stats ===\n\n");

        $activeCount = Submission::find()->count();
        $trashedCount = Submission::find()->trashed()->count();
        $totalCount = Submission::find()->status(null)->trashed(null)->count();

        $this->stdout("Active submissions:  {$activeCount}\n");
        $this->stdout("Trashed submissions: {$trashedCount}\n");
        $this->stdout("Total in DB:         {$totalCount}\n");

        return ExitCode::OK;
    }
}
