<?php
namespace verbb\fieldmanager\services;

use verbb\fieldmanager\FieldManager;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;

use yii\base\Component;

use Throwable;

use craft\ckeditor\Plugin as CkEditor;
use craft\ckeditor\Field as CkEditorField;
use craft\ckeditor\CkeConfig;

class Import extends Component
{
    // Public Methods
    // =========================================================================

    public function prepFieldsForImport($fields, $data): array
    {
        $fieldsToImport = [];

        foreach ($fields as $key => $field) {
            if (isset($field['import']) && $field['import'] != 'noimport') {

                // Get the field data from our imported JSON data
                $fieldsToImport[$key] = $data[$key];

                // Handle overrides
                $fieldsToImport[$key]['name'] = $field['name'];
                $fieldsToImport[$key]['handle'] = $field['handle'];

                // Handle Matrix
                if ($data[$key]['type'] === 'craft\fields\Matrix') {
                    $entryTypes = $field['settings']['entryTypes'] ?? [];

                    foreach ($entryTypes as $entryTypeKey => $entryType) {
                        $entryTypeImport = ArrayHelper::remove($entryType, 'import');

                        // Remove the whole block if not importing
                        if ($entryTypeImport === 'noimport') {
                            unset($fieldsToImport[$key]['settings']['entryTypes'][$entryTypeKey]);

                            continue;
                        }

                        // Create and save the entry types first. Matrix doesn't do this in one go anymore
                        $newEntryType = new EntryType([
                            'name' => $entryType['name'],
                            'handle' => $entryType['handle'],
                        ]);

                        // Generate a new handle if already taken
                        if ($currentEntryType = Craft::$app->getEntries()->getEntryTypeByHandle($newEntryType->handle)) {
                            $newEntryType->handle .= StringHelper::randomString(10);
                        }

                        $fieldLayoutConfig = $fieldsToImport[$key]['settings']['entryTypes'][$entryTypeKey]['fieldLayout'] ?? [];

                        if (isset($fieldLayoutConfig['tabs']) && is_array($fieldLayoutConfig['tabs'])) {
                            foreach ($fieldLayoutConfig['tabs'] as $tabKey => $tab) {
                                $fieldLayoutConfig['tabs'][$tabKey]['uid'] = null;

                                if (isset($tab['elements']) && is_array($tab['elements'])) {
                                    foreach ($tab['elements'] as $elementKey => $fieldElement) {
                                        $fieldLayoutConfig['tabs'][$tabKey]['elements'][$elementKey]['uid'] = null;
                                        $fieldLayoutConfig['tabs'][$tabKey]['elements'][$elementKey]['dateAdded'] = null;
                                    }
                                }
                            }
                        }

                        $fieldLayout = FieldLayout::createFromConfig($fieldLayoutConfig);
                        $newEntryType->setFieldLayout($fieldLayout);

                        if (Craft::$app->getEntries()->saveEntryType($newEntryType)) {
                            $fieldsToImport[$key]['settings']['entryTypes'][$entryTypeKey] = $newEntryType;
                        } else {
                            unset($fieldsToImport[$key]['settings']['entryTypes'][$entryTypeKey]);

                            continue;
                        }
                    }
                }

                // Handle Super Table
                if ($data[$key]['type'] === 'verbb\supertable\fields\SuperTableField') {
                    $blockTypes = $field['settings']['blockTypes'] ?? [];

                    foreach ($blockTypes as $blockTypeKey => $blockType) {
                        $blockTypeFields = $blockType['fields'] ?? [];

                        foreach ($blockTypeFields as $blockTypeFieldKey => $blockTypeField) {
                            $blockTypeFieldImport = ArrayHelper::remove($blockTypeField, 'import');

                            // Remove the whole field if not importing
                            if ($blockTypeFieldImport === 'noimport') {
                                unset($fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['fields'][$blockTypeFieldKey]);

                                continue;
                            }

                            // Update name and handles for blocktype
                            $fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['fields'][$blockTypeFieldKey]['name'] = $blockTypeField['name'];
                            $fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['fields'][$blockTypeFieldKey]['handle'] = $blockTypeField['handle'];
                        }
                    }
                }

                // Handle Neo
                if ($data[$key]['type'] === 'benf\neo\Field') {
                    $blockTypes = $field['settings']['blockTypes'] ?? [];

                    foreach ($blockTypes as $blockTypeKey => $blockType) {
                        $blockTypeImport = ArrayHelper::remove($blockType, 'import');

                        // Remove the whole block if not importing
                        if ($blockTypeImport === 'noimport') {
                            unset($fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]);

                            continue;
                        }

                        // Update name and handles for blocktype
                        $fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['name'] = $blockType['name'] ?? '';
                        $fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['handle'] = $blockType['handle'] ?? '';

                        $blockTypeTabs = $blockType['fieldLayout'] ?? [];

                        foreach ($blockTypeTabs as $blockTypeTabKey => $blockTypeTab) {
                            foreach ($blockTypeTab as $blockTypeFieldKey => $blockTypeField) {
                                $blockTypeFieldImport = ArrayHelper::remove($blockTypeField, 'import');

                                // Remove the whole field if not importing
                                if ($blockTypeFieldImport === 'noimport') {
                                    unset($fieldsToImport[$key]['settings']['blockTypes'][$blockTypeKey]['fieldLayout'][$blockTypeTabKey][$blockTypeFieldKey]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $fieldsToImport;
    }

    public function import(array $fields): array
    {
        $fieldTypes = Craft::$app->getFields()->getAllFieldTypes();
        $errors = [];

        foreach ($fields as $fieldInfo) {
            // Check for older (pre Craft 2) imports, where fields weren't namespaced
            if (!str_contains($fieldInfo['type'], '\\')) {
                // There's lots we need to do here!
                $this->_processCraft2Fields($fieldInfo);
            }

            if (in_array($fieldInfo['type'], $fieldTypes, false)) {
                if ($fieldInfo['type'] == 'craft\fields\Matrix') {
                    $fieldInfo['settings'] = $this->processMatrix($fieldInfo);
                }

                if ($fieldInfo['type'] == 'verbb\supertable\fields\SuperTableField') {
                    $fieldInfo['settings'] = $this->processSuperTable($fieldInfo);
                }

                if ($fieldInfo['type'] == 'benf\neo\Field') {
                    $fieldInfo['settings'] = $this->processNeo($fieldInfo);
                }

                if ($fieldInfo['type'] == 'rias\positionfieldtype\fields\Position') {
                    $fieldInfo['settings'] = $this->processPosition($fieldInfo);
                }

                if ($fieldInfo['type'] === 'craft\ckeditor\Field') {
                    $fieldInfo['settings'] = $this->processCkEditor($fieldInfo);
                }

                $field = Craft::$app->getFields()->createField([
                    'name' => $fieldInfo['name'],
                    'handle' => $fieldInfo['handle'],
                    'instructions' => $fieldInfo['instructions'],
                    'searchable' => $fieldInfo['searchable'],
                    'translationMethod' => $fieldInfo['translationMethod'] ?? '',
                    'translationKeyFormat' => $fieldInfo['translationKeyFormat'] ?? '',
                    'required' => $fieldInfo['required'],
                    'type' => $fieldInfo['type'],
                    'settings' => $fieldInfo['settings'],
                ]);

                // Send off to Craft's native fieldSave service for heavy lifting.
                if (!Craft::$app->getFields()->saveField($field)) {
                    $fieldErrors = $field->getErrors();

                    // Handle Matrix/Super Table errors
                    if ($fieldInfo['type'] == 'craft\fields\Matrix') {
                        $errors[$fieldInfo['handle']] = $fieldErrors;

                        foreach ($field->getEntryTypes() as $entryType) {
                            foreach ($entryType->getCustomFields() as $entryTypeField) {
                                if ($entryTypeField->hasErrors()) {
                                    $errors[$fieldInfo['handle']][$entryTypeField->handle] = $entryTypeField->getErrors();
                                }
                            }
                        }
                    } else if ($fieldInfo['type'] == 'verbb\supertable\fields\SuperTableField') {
                        foreach ($field->getBlockTypes() as $blockType) {
                            foreach ($blockType->getCustomFields() as $blockTypeField) {
                                if ($blockTypeField->hasErrors()) {
                                    $errors[$fieldInfo['handle']][$blockTypeField->handle] = $blockTypeField->getErrors();
                                }
                            }
                        }
                    } else {
                        $errors[$fieldInfo['handle']] = $fieldErrors;
                    }

                    FieldManager::error('Could not import {name} - {errors}.', [
                        'name' => $fieldInfo['name'],
                        'errors' => print_r($fieldErrors, true),
                    ]);
                }
            } else {
                FieldManager::error('Unsupported field "{field}".', [
                    'field' => $fieldInfo['type'],
                ]);
            }
        }

        return $errors;
    }

    public function processMatrix($fieldInfo): array
    {
        $settings = $fieldInfo['settings'];

        if (isset($settings['blockTypes'])) {
            foreach ($settings['blockTypes'] as $i => $blockType) {
                foreach ($blockType['fields'] as $j => $blockTypeField) {
                    $preppedSettings['settings'] = $blockTypeField['typesettings'];

                    if ($blockTypeField['type'] == 'rias\positionfieldtype\fields\Position') {
                        $settings['blockTypes'][$i]['fields'][$j]['typesettings'] = $this->processPosition($preppedSettings);
                    }
                }
            }
        }

        return $settings;
    }

    public function processSuperTable($fieldInfo): array
    {
        $settings = $fieldInfo['settings'];

        if (isset($settings['blockTypes'])) {
            foreach ($settings['blockTypes'] as $i => $blockType) {
                foreach ($blockType['fields'] as $j => $blockTypeField) {
                    $preppedSettings['settings'] = $blockTypeField['typesettings'];

                    if ($blockTypeField['type'] == 'rias\positionfieldtype\fields\Position') {
                        $settings['blockTypes'][$i]['fields'][$j]['typesettings'] = $this->processPosition($preppedSettings);
                    }
                }
            }
        }

        return $settings;
    }

    public function processNeo($fieldInfo): array
    {
        $settings = $fieldInfo['settings'];
        $fieldsService = Craft::$app->fields;

        if (isset($settings['blockTypes'])) {
            foreach ($settings['blockTypes'] as $i => $blockType) {
                if ($blockType['fieldLayout'] === null) {
                    continue;
                }

                $fieldLayout = FieldManager::$plugin->getService()->createFieldLayoutFromConfig($blockType['fieldLayout']);

                // Have to save it now, and apply the ID to the blockType, as Neo won't save it for us
                // due to it relying on building the field layout with `assembleLayoutFromPost` which is impossible
                // for us to use in this circumstance.
                Craft::$app->getFields()->saveLayout($fieldLayout);

                // Remove the field layout config and apply the ID
                unset($settings['blockTypes'][$i]['fieldLayout']);
                $settings['blockTypes'][$i]['fieldLayoutId'] = $fieldLayout->id;
            }
        }

        return $settings;
    }

    public function processPosition($fieldInfo): array
    {
        $settings = $fieldInfo['settings'];

        // Position field can't handle numbers for the toggle switches (this is probably incorrect in the plugin)
        // but let's be nice and fix it here. This is also the format in the export.
        if (isset($settings['options'])) {
            foreach ($settings['options'] as $key => $value) {
                $settings['options'][$key] = (string)$value;
            }
        }

        return $settings;
    }

    public function processCkEditor($fieldInfo): array
    {
        $settings = $fieldInfo['settings'];

        // Get or create the config from its UID
        $ckeConfigData = $settings['ckeConfig'] ?? null;
        $ckeConfigUid = $settings['ckeConfig']['uid'] ?? null;

        if ($ckeConfigUid) {
            try {
                $ckeConfig = CkEditor::getInstance()->getCkeConfigs()->getByUid($ckeConfigUid);
            } catch (Throwable) {
                $ckeConfig = null;
            }

            if (!$ckeConfig) {
                $ckeConfig = new CkeConfig($ckeConfigData);

                CkEditor::getInstance()->getCkeConfigs()->save($ckeConfig);

                $ckeConfigUid = $ckeConfig->uid;
            }

            $settings['ckeConfig'] = $ckeConfigUid;
        }

        return $settings;
    }

    public function getData($json)
    {
        $data = Json::decode($json, true, 512);

        if ($data === null) {
            FieldManager::error('Could not parse JSON data - {error}.', ['error' => json_last_error_msg()]);

            return false;
        }

        return $data;
    }

    private function _processCraft2Fields(&$fieldInfo): array
    {
        // There are (likely) a bunch of cases to deal with for Craft 2 - Craft 3 fields. Add them here...
        // If we don't convert them to the new counterparts, we'll get critical CP errors

        if (isset($fieldInfo['settings']['targetLocale'])) {
            unset($fieldInfo['settings']['targetLocale']);
        }

        if (isset($fieldInfo['typesettings']['targetLocale'])) {
            unset($fieldInfo['typesettings']['targetLocale']);
        }


        if (isset($fieldInfo['settings']['maxLength'])) {
            $fieldInfo['settings']['charLimit'] = $fieldInfo['settings']['maxLength'];
            unset($fieldInfo['settings']['maxLength']);
        }

        if (isset($fieldInfo['typesettings']['maxLength'])) {
            $fieldInfo['typesettings']['charLimit'] = $fieldInfo['typesettings']['maxLength'];
            unset($fieldInfo['typesettings']['maxLength']);
        }


        if ($fieldInfo['type'] == 'Categories') {
            if (isset($fieldInfo['settings']['limit'])) {
                $fieldInfo['settings']['branchLimit'] = $fieldInfo['settings']['limit'];
                unset($fieldInfo['settings']['limit']);
            }

            if (isset($fieldInfo['typesettings']['limit'])) {
                $fieldInfo['typesettings']['branchLimit'] = $fieldInfo['typesettings']['limit'];
                unset($fieldInfo['typesettings']['limit']);
            }
        }

        // Matrix needs to loop through each blocktype's field to update the type
        // Do some tricky recursive goodness to deal with all the fields in each block
        if ($fieldInfo['type'] == 'Matrix') {
            foreach ($fieldInfo['settings']['blockTypes'] as $blockHandle => $blockType) {
                foreach ($blockType['fields'] as $key => $field) {
                    $fieldInfo['settings']['blockTypes'][$blockHandle]['fields'][$key] = $this->_processCraft2Fields($field);
                }
            }

            if (isset($fieldInfo['translatable']) && $fieldInfo['translatable']) {
                $fieldInfo['settings']['localizeBlocks'] = 1;
                unset($fieldInfo['translatable']);
            }
        }

        // Use the namespaced format for the type, which is Craft 3
        $fieldInfo['type'] = 'craft\\fields\\' . $fieldInfo['type'];

        // If the field is translatable, we set the Translation Method to each language
        if (isset($fieldInfo['translatable']) && $fieldInfo['translatable']) {
            $fieldInfo['translationMethod'] = 'language';
            unset($fieldInfo['translatable']);
        }

        return $fieldInfo;
    }
}