<?php
namespace verbb\fieldmanager\services;

use verbb\fieldmanager\helpers\Plugin;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\fields\Matrix;

use yii\base\Component;

use Throwable;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use benf\neo\Plugin as Neo;
use benf\neo\Field as NeoField;

use craft\ckeditor\Plugin as CkEditor;
use craft\ckeditor\Field as CkEditorField;

class Export extends Component
{
    // Public Methods
    // =========================================================================

    public function export(array $fieldIds): array
    {
        $fields = [];

        foreach ($fieldIds as $fieldId) {
            $field = Craft::$app->getFields()->getFieldById($fieldId);

            if ($field) {
                $newField = [
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'instructions' => $field->instructions,
                    'required' => $field->required,
                    'searchable' => $field->searchable,
                    'translationMethod' => $field->translationMethod,
                    'translationKeyFormat' => $field->translationKeyFormat,
                    'type' => $field::class,
                    'settings' => $field->settings,
                ];

                if ($field instanceof Matrix) {
                    $newField['settings'] = $this->processMatrix($field);
                }

                if (Plugin::isPluginInstalledAndEnabled('super-table')) {
                    if ($field instanceof SuperTableField) {
                        $newField['settings'] = $this->processSuperTable($field);
                    }
                }

                if (Plugin::isPluginInstalledAndEnabled('neo')) {
                    if ($field instanceof NeoField) {
                        $newField['settings'] = $this->processNeo($field);
                    }
                }

                if (Plugin::isPluginInstalledAndEnabled('ckeditor')) {
                    if ($field instanceof CkEditorField) {
                        $newField['settings'] = $this->processCkEditor($field);
                    }
                }

                $fields[] = $newField;
            }
        }

        return $fields;
    }

    public function processMatrix($field): array
    {
        $fieldSettings = $field->settings;

        $blockTypes = $field->getEntryTypes();

        $blockCount = 1;
        foreach ($blockTypes as $blockType) {
            $fieldSettings['blockTypes']['new' . $blockCount] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'fields' => [],
            ];

            $fieldCount = 1;
            foreach ($blockType->getCustomFields() as $blockField) {
                // Case for nested Super Table
                if ($blockField::class == 'verbb\supertable\fields\SuperTableField') {
                    $settings = $this->processSuperTable($blockField);
                } else {
                    $settings = $blockField->settings;
                }

                $width = 100;
                $fieldLayout = $blockType->getFieldLayout();
                $fieldLayoutElements = $fieldLayout->getTabs()[0]->elements ?? [];

                if ($fieldLayoutElements) {
                    $fieldLayoutElement = ArrayHelper::firstWhere($fieldLayoutElements, 'field.uid', $blockField->uid);
                    $width = (int)($fieldLayoutElement->width ?? 0) ?: 100;
                }

                $fieldSettings['blockTypes']['new' . $blockCount]['fields']['new' . $fieldCount] = [
                    'name' => $blockField->name,
                    'handle' => $blockField->handle,
                    'required' => $blockField->required,
                    'instructions' => $blockField->instructions,
                    'searchable' => $blockField->searchable,
                    'translationMethod' => $blockField->translationMethod,
                    'translationKeyFormat' => $blockField->translationKeyFormat,
                    'type' => $blockField::class,
                    'typesettings' => $settings,
                    'width' => $width,
                ];

                $fieldCount++;
            }

            $blockCount++;
        }

        return $fieldSettings;
    }

    public function processNeo($field): array
    {
        $fieldSettings = $field->settings;

        $blockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);
        $groups = Neo::$plugin->blockTypes->getGroupsByFieldId($field->id);

        foreach ($groups as $group) {
            $fieldSettings['groups'][] = [
                'name' => $group->name,
                'sortOrder' => $group->sortOrder,
                'alwaysShowDropdown' => $group->alwaysShowDropdown,
            ];
        }

        foreach ($blockTypes as $i => $blockType) {
            $childBlocks = $blockType->childBlocks;

            if (!is_array($childBlocks)) {
                $childBlocks = Json::decodeIfJson((string)$childBlocks);
            }

            $fieldSettings['blockTypes']['new' . ($i + 1)] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'description' => $blockType->description,
                'ignorePermissions' => (bool)$blockType->ignorePermissions,
                'enabled' => (bool)$blockType->enabled,
                'iconId' => $blockType->iconId,
                'minEntries' => $blockType->minEntries,
                'maxEntries' => (int)$blockType->maxEntries,
                'minSiblingBlocks' => $blockType->minSiblingBlocks,
                'maxSiblingBlocks' => (int)$blockType->maxSiblingBlocks,
                'minChildBlocks' => (int)$blockType->minChildBlocks,
                'maxChildBlocks' => (int)$blockType->maxChildBlocks,
                'childBlocks' => $childBlocks,
                'groupChildBlockTypes' => (bool)$blockType->groupChildBlockTypes,
                'topLevel' => (bool)$blockType->topLevel,
                'fieldLayout' => $blockType->fieldLayout->getConfig(),
                'conditions' => $blockType->conditions,
                'sortOrder' => (int)$blockType->sortOrder,
            ];
        }

        return $fieldSettings;
    }

    public function processSuperTable($field): array
    {
        $fieldSettings = $field->settings;

        $blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($field->id);

        $blockCount = 1;
        foreach ($blockTypes as $blockType) {
            $fieldSettings['blockTypes']['new' . $blockCount] = [
                'fields' => [],
            ];

            $fieldCount = 1;
            foreach ($blockType->getCustomFields() as $blockField) {
                // Case for nested Matrix
                if ($blockField::class == Matrix::class) {
                    $settings = $this->processMatrix($blockField);
                } else {
                    $settings = $blockField->settings;
                }

                $width = 100;
                $fieldLayout = $blockType->getFieldLayout();
                $fieldLayoutElements = $fieldLayout->getTabs()[0]->elements ?? [];

                if ($fieldLayoutElements) {
                    $fieldLayoutElement = ArrayHelper::firstWhere($fieldLayoutElements, 'field.uid', $blockField->uid);
                    $width = (int)($fieldLayoutElement->width ?? 0) ?: 100;
                }

                $fieldSettings['blockTypes']['new' . $blockCount]['fields']['new' . $fieldCount] = [
                    'name' => $blockField->name,
                    'handle' => $blockField->handle,
                    'required' => $blockField->required,
                    'instructions' => $blockField->instructions,
                    'searchable' => $blockField->searchable,
                    'translationMethod' => $blockField->translationMethod,
                    'translationKeyFormat' => $blockField->translationKeyFormat,
                    'type' => $blockField::class,
                    'typesettings' => $settings,
                    'width' => $width,
                ];

                $fieldCount++;
            }

            $blockCount++;
        }

        return $fieldSettings;
    }

    public function processCkEditor($field): array
    {
        $fieldSettings = $field->settings;
        $ckeConfigUid = $fieldSettings['ckeConfig'] ?? null;

        if ($ckeConfigUid) {
            try {
                $ckeConfig = CkEditor::getInstance()->getCkeConfigs()->getByUid($ckeConfigUid);

                if ($ckeConfig) {
                    $fieldSettings['ckeConfig'] = $ckeConfig;
                }
            } catch (Throwable) {
            }
        }

        return $fieldSettings;
    }
}
