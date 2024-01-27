<?php
namespace verbb\fieldmanager\controllers;

use verbb\fieldmanager\FieldManager;
use verbb\fieldmanager\models\Settings;

use Craft;
use craft\base\Field;
use craft\fields\MissingField;
use craft\fields\PlainText;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\FieldGroup;
use craft\web\Controller;

use yii\web\Response;
use yii\web\ServerErrorHttpException;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $variables = [];
        $variables['unusedFieldIds'] = FieldManager::$plugin->getService()->getUnusedFieldIds();

        return $this->renderTemplate('field-manager/index', $variables);
    }

    public function actionSettings(): Response
    {
        /* @var Settings $settings */
        $settings = FieldManager::$plugin->getSettings();

        return $this->renderTemplate('field-manager/settings', [
            'settings' => $settings,
        ]);
    }

    public function actionGetGroupModalBody(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();

        $groupId = $request->getBodyParam('groupId');

        $group = null;
        $prefix = null;

        if ($groupId) {
            $group = $fieldsService->getGroupById($groupId);
            $prefix = StringHelper::toCamelCase($group->name) . '_';
        }

        $variables = [
            'group' => $group,
            'prefix' => $prefix,
            'clone' => $request->getBodyParam('clone'),
        ];

        $html = $this->getView()->renderTemplate('field-manager/_group/group_edit', $variables);

        return $this->asJson([
            'success' => true,
            'html' => $html,
        ]);
    }

    public function actionGetFieldModalBody(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $view = $this->getView();

        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();

        $fieldId = (int)$request->getBodyParam('fieldId');
        $groupId = (int)$request->getBodyParam('groupId');

        // The field
        // ---------------------------------------------------------------------

        $field = null;
        $missingFieldPlaceholder = null;

        if ($field === null && $fieldId !== null) {
            $field = $fieldsService->getFieldById($fieldId);

            if ($field instanceof MissingField) {
                $missingFieldPlaceholder = $field->getPlaceholderHtml();
                $field = $field->createFallback(PlainText::class);
            }
        }

        if ($field === null) {
            $field = $fieldsService->createField(PlainText::class);
        }

        // Supported translation methods
        // ---------------------------------------------------------------------

        $supportedTranslationMethods = [];

        $allFieldTypes = $fieldsService->getAllFieldTypes();

        foreach ($allFieldTypes as $class) {
            if ($class === $field::class || $class::isSelectable()) {
                $supportedTranslationMethods[$class] = $class::supportedTranslationMethods();
            }
        }

        // Allowed field types
        // ---------------------------------------------------------------------

        if (!$field->id) {
            $compatibleFieldTypes = $allFieldTypes;
        } else {
            $compatibleFieldTypes = $fieldsService->getCompatibleFieldTypes($field, true);
        }

        $fieldTypeOptions = [];

        foreach ($allFieldTypes as $class) {
            if ($class === $field::class || $class::isSelectable()) {
                $compatible = in_array($class, $compatibleFieldTypes, true);
                $fieldTypeOptions[] = [
                    'value' => $class,
                    'label' => $class::displayName() . ($compatible ? '' : ' ⚠️'),
                ];
            }
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypeOptions, 'label');

        // Groups
        // ---------------------------------------------------------------------

        $allGroups = $fieldsService->getAllGroups();

        if (empty($allGroups)) {
            throw new ServerErrorHttpException('No field groups exist');
        }

        if ($groupId === null) {
            $groupId = ($field !== null && $field->groupId !== null) ? $field->groupId : $allGroups[0]->id;
        }

        $fieldGroup = $fieldsService->getGroupById($groupId);

        $groupOptions = [];

        foreach ($allGroups as $group) {
            $groupOptions[] = [
                'value' => $group->id,
                'label' => $group->name,
            ];
        }

        $variables = [
            'fieldId' => $fieldId,
            'field' => $field,
            'allFieldTypes' => $allFieldTypes,
            'fieldTypeOptions' => $fieldTypeOptions,
            'missingFieldPlaceholder' => $missingFieldPlaceholder,
            'supportedTranslationMethods' => $supportedTranslationMethods,
            'compatibleFieldTypes' => $compatibleFieldTypes,
            'groupId' => $groupId,
            'groupOptions' => $groupOptions,
        ];

        $html = $view->renderTemplate('field-manager/_single/field_edit', $variables);

        $headHtml = $view->getHeadHtml();
        $footHtml = $view->getBodyHtml();

        return $this->asJson([
            'success' => true,
            'html' => $html,
            'headHtml' => $headHtml,
            'footHtml' => $footHtml,
        ]);
    }

    public function actionCloneField(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $fieldId = Craft::$app->getRequest()->getRequiredBodyParam('fieldId');

        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();
        $type = $request->getRequiredBodyParam('type');

        $field = $fieldsService->createField([
            'type' => $type,
            'groupId' => $request->getRequiredBodyParam('group'),
            'name' => $request->getBodyParam('name'),
            'handle' => $request->getBodyParam('handle'),
            'instructions' => $request->getBodyParam('instructions'),
            'searchable' => (bool)$request->getBodyParam('searchable', true),
            'translationMethod' => $request->getBodyParam('translationMethod', Field::TRANSLATION_METHOD_NONE),
            'translationKeyFormat' => $request->getBodyParam('translationKeyFormat'),
            'settings' => $request->getBodyParam('types.' . $type),
        ]);

        $originField = $fieldsService->getFieldById($fieldId);

        if (!FieldManager::$plugin->getService()->cloneField($field, $originField)) {
            return $this->asFailure(Json::encode($field->getErrors()));
        }

        return $this->asSuccess(null, ['fieldId' => $field->id]);
    }

    public function actionCloneGroup(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $groupId = Craft::$app->getRequest()->getRequiredBodyParam('groupId');
        $prefix = Craft::$app->getRequest()->getRequiredBodyParam('prefix');

        $group = new FieldGroup();
        $group->name = Craft::$app->getRequest()->getRequiredBodyParam('name');

        $originGroup = Craft::$app->getFields()->getGroupById($groupId);

        if (!FieldManager::$plugin->getService()->cloneGroup($group, $prefix, $originGroup)) {
            return $this->asFailure(Json::encode($group->getErrors()));
        }

        return $this->asSuccess(null, ['groupId' => $group->id]);
    }

    // From Craft's native saveField, which doesn't really support Ajax...
    public function actionSaveField(): Response
    {
        $this->requirePostRequest();

        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();
        $type = $request->getRequiredBodyParam('type');

        $field = $fieldsService->createField([
            'type' => $type,
            'id' => (int)$request->getBodyParam('fieldId') ?: null,
            'groupId' => $request->getRequiredBodyParam('group'),
            'name' => $request->getBodyParam('name'),
            'handle' => $request->getBodyParam('handle'),
            'instructions' => $request->getBodyParam('instructions'),
            'searchable' => (bool)$request->getBodyParam('searchable', true),
            'translationMethod' => $request->getBodyParam('translationMethod', Field::TRANSLATION_METHOD_NONE),
            'translationKeyFormat' => $request->getBodyParam('translationKeyFormat'),
            'settings' => $request->getBodyParam('types.' . $type),
        ]);

        if (!$fieldsService->saveField($field)) {
            return $this->asFailure(Json::encode($field->getErrors()));
        }

        return $this->asSuccess();
    }

    public function actionExport(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $fields = $request->getParam('selectedFields');
        $download = $request->getParam('download');

        if (count($fields) > 0) {
            $fieldsObj = FieldManager::$plugin->getExport()->export($fields);

            $json = Json::encode($fieldsObj, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);

            if ($download) {
                Craft::$app->getResponse()->sendContentAsFile($json, 'export.json');
                Craft::$app->end();
            } else {
                return $this->renderTemplate('field-manager/export', [
                    'json' => $json,
                ]);
            }
        }

        Craft::$app->getSession()->setError(Craft::t('field-manager', 'Could not export data.'));

        return null;
    }

    public function actionMapFields(): ?Response
    {
        $this->requirePostRequest();

        $json = Craft::$app->getRequest()->getParam('data', '{}');
        $data = FieldManager::$plugin->getImport()->getData($json);

        if ($data) {
            return $this->renderTemplate('field-manager/import/map', [
                'fields' => $data,
                'errors' => [],
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('field-manager', 'Could not parse JSON data.'));

        return null;
    }

    public function actionImport(): ?Response
    {
        $this->requirePostRequest();

        /** @var array $fields */
        $fields = Craft::$app->getRequest()->getBodyParam('fields', '');
        $json = Craft::$app->getRequest()->getBodyParam('data', '{}');
        $data = FieldManager::$plugin->getImport()->getData($json);

        $fieldsToImport = FieldManager::$plugin->getImport()->prepFieldsForImport($fields, $data);

        if ($fieldsToImport) {
            $importErrors = FieldManager::$plugin->getImport()->import($fieldsToImport);

            if (!$importErrors) {
                Craft::$app->getSession()->setSuccess(Craft::t('field-manager', 'Imported successfully.'));
                return null;
            } else {
                Craft::$app->getSession()->setError(Craft::t('field-manager', 'Error importing fields.'));

                return $this->renderTemplate('field-manager/import/map', [
                    'fields' => $fieldsToImport,
                    'errors' => $importErrors,
                ]);
            }
        }

        Craft::$app->getSession()->setNotice(Craft::t('field-manager', 'No fields imported.'));

        return null;
    }
}
