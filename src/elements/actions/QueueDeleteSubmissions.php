<?php

namespace hybridinteractive\contactformextensions\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use hybridinteractive\contactformextensions\jobs\DeleteSubmissionsJob;

class QueueDeleteSubmissions extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('contact-form-extensions', 'Delete (queued)');
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('contact-form-extensions', 'Are you sure you want to delete the selected submissions? This will queue them for deletion in the background.');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $ids = $query->ids();

        Craft::$app->queue->push(new DeleteSubmissionsJob([
            'ids' => $ids,
        ]));

        $this->setMessage(Craft::t('contact-form-extensions', 'Deletion queued.'));

        return true;
    }
}
