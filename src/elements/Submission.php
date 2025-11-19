<?php

namespace hybridinteractive\contactformextensions\elements;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use hybridinteractive\contactformextensions\elements\db\SubmissionQuery;

class Submission extends Element
{
    public ?string $form = null;
    public ?string $fromName = null;
    public ?string $fromEmail = null;
    public ?string $subject = null;
    public $message;

    // ─────────────────────────────────────────
    // Core element behavior
    // ─────────────────────────────────────────

    public static function hasContent(): bool
    {
        // All data lives in contactform_submissions, not content table
        return false;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    public function canView($user): bool
    {
        return true;
    }

    public function canDelete($user): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return new SubmissionQuery(static::class);
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['form', 'subject', 'fromName', 'fromEmail'];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('contact-form-extensions/submissions/' . $this->id);
    }

    // ─────────────────────────────────────────
    // Sources (left-hand filter in CP index)
    // ─────────────────────────────────────────

    protected static function defineSources(?string $context = null): array
    {
        $sources = [
            [
                'key'      => '*',
                'label'    => Craft::t('contact-form-extensions', 'All submissions'),
                'criteria' => [],
                'default'  => true,
            ],
        ];

        // ⚠ Previously this did self::find()->all() → catastrophic on 167k rows
        // Use a lightweight DB query to get distinct form handles instead
        $forms = (new Query())
            ->select(['form'])
            ->from('{{%contactform_submissions}}')
            ->where(['not', ['form' => null]])
            ->distinct()
            ->orderBy(['form' => SORT_ASC])
            ->column();

        foreach ($forms as $formHandle) {
            $sources[] = [
                'key'      => 'form:' . $formHandle,
                'label'    => ucfirst($formHandle),
                'criteria' => ['form' => $formHandle],
            ];
        }

        return $sources;
    }

    // ─────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────

    protected static function defineActions(?string $source = null): array
    {
        $elementsService = Craft::$app->getElements();
        $actions = parent::defineActions($source);

        // $actions[] = $elementsService->createAction([
        //     'type'                => Delete::class,
        //     'confirmationMessage' => Craft::t('contact-form-extensions', 'Are you sure you want to delete the selected submissions?'),
        //     'successMessage'      => Craft::t('contact-form-extensions', 'Submissions deleted.'),
        // ]);

        $actions[] = $elementsService->createAction([
            'type' => \hybridinteractive\contactformextensions\elements\actions\QueueDeleteSubmissions::class,
        ]);

        return $actions;
    }

    // ─────────────────────────────────────────
    // Table columns
    // ─────────────────────────────────────────

    protected static function defineTableAttributes(): array
    {
        return [
            'id'          => Craft::t('contact-form-extensions', 'ID'),
            'form'        => Craft::t('contact-form-extensions', 'Form'),
            'subject'     => Craft::t('contact-form-extensions', 'Subject'),
            'fromName'    => Craft::t('contact-form-extensions', 'From Name'),
            'fromEmail'   => Craft::t('contact-form-extensions', 'From Email'),
            'message'     => Craft::t('contact-form-extensions', 'Message'),
            'dateCreated' => Craft::t('contact-form-extensions', 'Date Created'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'id',
            'form',
            'subject',
            'fromName',
            'fromEmail',
            'message',
            'dateCreated',
        ];
    }

    public function getTableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'message') {
            $message = (array) json_decode($this->message);
            $html = '<ul>';

            foreach ($message as $key => $value) {
                if (
                    is_string($value) &&
                    !in_array($key, [
                        'formName',
                        'toEmail',
                        'confirmationSubject',
                        'confirmationTemplate',
                        'notificationTemplate',
                        'disableRecaptcha',
                        'disableConfirmation',
                    ], true)
                ) {
                    $shortened = trim(substr($value, 0, 30));
                    $html .= "<li><em>{$key}</em>: {$shortened}...</li>";
                }
            }

            $html .= '</ul>';

            return StringHelper::convertToUtf8($html);
        }

        return parent::getTableAttributeHtml($attribute);
    }

    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('app', 'Date Created'),
            'fromEmail'   => Craft::t('contact-form-extensions', 'From Email'),
            'form'        => Craft::t('contact-form-extensions', 'Form'),
        ];
    }

    // ─────────────────────────────────────────
    // Persistence
    // ─────────────────────────────────────────

    public function afterSave(bool $isNew): void
    {
        $db = Craft::$app->db;

        if ($isNew) {
            $db->createCommand()
                ->insert('{{%contactform_submissions}}', [
                    'id'        => $this->id,
                    'form'      => $this->form,
                    'subject'   => $this->subject,
                    'fromName'  => $this->fromName,
                    'fromEmail' => $this->fromEmail,
                    'message'   => $this->message,
                ])
                ->execute();
        } else {
            $db->createCommand()
                ->update('{{%contactform_submissions}}', [
                    'form'      => $this->form,
                    'subject'   => $this->subject,
                    'fromName'  => $this->fromName,
                    'fromEmail' => $this->fromEmail,
                    'message'   => $this->message,
                ], ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
