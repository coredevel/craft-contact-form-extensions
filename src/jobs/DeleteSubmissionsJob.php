<?php

namespace hybridinteractive\contactformextensions\jobs;

use Craft;
use craft\queue\BaseJob;
use hybridinteractive\contactformextensions\elements\Submission;

class DeleteSubmissionsJob extends BaseJob
{
    /** @var int[] */
    public array $ids = [];

    public function execute($queue): void
    {
        $elementsService = Craft::$app->getElements();
        $total = count($this->ids);

        if ($total === 0) {
            return;
        }

        /** @var \craft\elements\db\ElementQuery $query */
        $query = Submission::find()
            ->id($this->ids)
            ->status(null)     // IMPORTANT: allow deletion of disabled/draft/etc
            ->siteId('*')      // IMPORTANT: ensure it retrieves the element in any site
            ->limit(null);     // IMPORTANT: avoid Craft falling back to a default limit

        $i = 0;

        foreach ($query->each(100) as $submission) {
            // Hard delete = true to permanently remove from database
            $elementsService->deleteElement($submission, true);
            $this->setProgress($queue, ++$i / $total);
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('contact-form-extensions', 'Deleting submissions…');
    }
}
