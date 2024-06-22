<?php
namespace verbb\fieldmanager\services;

use verbb\fieldmanager\FieldManager;
use verbb\fieldmanager\helpers\Plugin;
use verbb\fieldmanager\models\Settings;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\fields\Matrix;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

use yii\base\Component;

use benf\neo\Field as NeoField;
use benf\neo\elements\Block;

use verbb\supertable\fields\SuperTableField;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function isCpSectionEnabled(): bool
    {
        /* @var Settings $settings */
        $settings = FieldManager::$plugin->getSettings();

        return isset($settings['cpSectionEnabled']) && $settings['cpSectionEnabled'];
    }

    public function cloneField(FieldInterface $field, FieldInterface $originField): bool
    {
        // If this is a Matrix or Super Table field, we need to do some pre-processing.
        // Because we're essentially editing a current field, we need to remove ID's for blocks and inner fields.
        // Not doing this will move all fields from one Matrix to another - instead of creating new ones.
        if ($field instanceof Matrix) {
            $field->entryTypes = $this->processCloneMatrix($originField);
        }

        if (Plugin::isPluginInstalledAndEnabled('super-table')) {
            if ($field instanceof SuperTableField) {
                $field->entryTypes = $this->processCloneSuperTable($originField);
            }
        }

        if (Plugin::isPluginInstalledAndEnabled('neo')) {
            if ($field instanceof NeoField) {
                $blockTypes = $this->processCloneNeo($originField);
                $groups = $this->processCloneNeoGroups($originField);
                $field->blockTypes = $blockTypes;
                $field->groups = $groups;

                // Reset the keys so we can get iterate
                $blockTypes = array_values($blockTypes);

                // Have to re-assign the fieldlayout after the blocktypes have been set - ugh.
                // This is because Neo's `setBlockTypes()` relies on POST data, which we don't have here.
                // So create the blocktype as normal, filling in all other info, then populate the fieldLayout now.
                foreach ($field->blockTypes as $key => $blockType) {
                    $fieldLayout = $blockTypes[$key]['fieldLayout'] ?? null;

                    if ($fieldLayout) {
                        $field->blockTypes[$key]->setFieldLayout($fieldLayout);
                    }
                }
            }
        }

        // Send off to Craft's native fieldSave service for heavy lifting.
        if (!Craft::$app->getFields()->saveField($field)) {
            FieldManager::error('Could not clone {name} - {errors}.', ['name' => $field->name, 'errors' => print_r($field->getErrors(), true)]);

            return false;
        }

        return true;
    }

    public function getUnusedFields(): array
    {
        // All fields
        $allFields = (new Query())
            ->select(['uid'])
            ->from(['{{%fields}}'])
            ->column();

        $usedFields = [];

        $layoutConfig = (new Query())
            ->select(['config'])
            ->from(['{{%fieldlayouts}}'])
            ->where(['not', ['config' => null]])
            ->column();

        foreach ($layoutConfig as $config) {
            $json = Json::decode($config);

            foreach (($json['tabs'] ?? []) as $tab) {
                foreach (($tab['elements'] ?? []) as $element) {
                    $fieldUid = $element['fieldUid'] ?? null;

                    if ($fieldUid) {
                        $usedFields[] = $fieldUid;
                    }
                }
            }
        }

        // Get only the unused fields
        return array_diff($allFields, $usedFields);
    }

    public function processCloneMatrix(FieldInterface $originField): array
    {
        $blockTypes = [];

        foreach ($originField->blockTypes as $i => $blockType) {
            $fields = [];
            $blockKey = 'new' . ($i + 1);

            foreach ($blockType->getCustomFields() as $j => $blockField) {
                $fieldKey = 'new' . ($j + 1);
                $width = 100;

                $fieldLayout = $blockType->getFieldLayout();
                $fieldLayoutElements = $fieldLayout->getTabs()[0]->elements ?? [];

                if ($fieldLayoutElements) {
                    $fieldLayoutElement = ArrayHelper::firstWhere($fieldLayoutElements, 'field.uid', $blockField->uid);
                    $width = (int)($fieldLayoutElement->width ?? 0) ?: 100;
                }

                if ($blockField::class == 'verbb\supertable\fields\SuperTableField') {
                    $blockField->contentTable = $blockField->contentTable ?? '';
                }

                $fields[$fieldKey] = [
                    'type' => $blockField::class,
                    'name' => $blockField['name'],
                    'handle' => $blockField['handle'],
                    'instructions' => $blockField['instructions'],
                    'required' => (bool)$blockField['required'],
                    'searchable' => (bool)$blockField['searchable'],
                    'translationMethod' => $blockField['translationMethod'],
                    'translationKeyFormat' => $blockField['translationKeyFormat'],
                    'typesettings' => Json::decode(Json::encode($blockField['settings'])),
                    'width' => $width,
                ];

                if ($blockField::class == 'verbb\supertable\fields\SuperTableField') {
                    $fields['new' . ($j + 1)]['typesettings']['blockTypes'] = $this->processCloneSuperTable($blockField);
                }
            }

            $blockTypes[$blockKey] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'sortOrder' => $blockType->sortOrder,
                'fields' => $fields,
            ];
        }

        return $blockTypes;
    }

    public function processCloneNeo(FieldInterface $originField): array
    {
        $blockTypes = [];

        foreach ($originField->blockTypes as $i => $blockType) {
            $layout = new FieldLayout();
            $layout->type = Block::class;

            $tabs = [];

            foreach ($blockType->fieldLayout->getTabs() as $oldTab) {
                $tab = new FieldLayoutTab();
                $tab->layout = $layout;
                $tab->name = $oldTab->name;
                $tab->sortOrder = $oldTab->sortOrder;
                $tab->elements = $oldTab->elements;

                $tabs[] = $tab;
            }

            $layout->setTabs($tabs);

            $blockTypes['new' . $i] = [
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'description' => $blockType->description,
                'ignorePermissions' => $blockType->ignorePermissions,
                'enabled' => $blockType->enabled,
                'iconId' => $blockType->iconId,
                'minEntries' => $blockType->minEntries,
                'maxEntries' => $blockType->maxEntries,
                'minSiblingBlocks' => $blockType->minSiblingBlocks,
                'maxSiblingBlocks' => $blockType->maxSiblingBlocks,
                'minChildBlocks' => $blockType->minChildBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
                'groupChildBlockTypes' => $blockType->groupChildBlockTypes,
                'topLevel' => (bool)$blockType->topLevel,
                'fieldLayout' => $layout,
                'conditions' => $blockType->conditions,
                'sortOrder' => $blockType->sortOrder,
            ];
        }

        return $blockTypes;
    }

    public function processCloneNeoGroups(FieldInterface $originField): array
    {
        $groups = [];

        foreach ($originField->groups as $i => $group) {
            $groups['new' . $i] = [
                'name' => $group->name,
                'sortOrder' => $group->sortOrder,
                'alwaysShowDropdown' => $group->alwaysShowDropdown,
            ];
        }

        return $groups;
    }

    public function processCloneSuperTable(FieldInterface $originField): array
    {
        $blockTypes = [];

        foreach ($originField->blockTypes as $i => $blockType) {
            $fields = [];

            foreach ($blockType->getCustomFields() as $j => $blockField) {
                if ($blockField::class == Matrix::class) {
                    $blockField->contentTable = $blockField->contentTable ?? '';
                }

                $fields['new' . $j] = [
                    'type' => $blockField::class,
                    'name' => $blockField['name'],
                    'handle' => $blockField['handle'],
                    'instructions' => $blockField['instructions'],
                    'required' => (bool)$blockField['required'],
                    'searchable' => (bool)$blockField['searchable'],
                    'translationMethod' => $blockField['translationMethod'],
                    'translationKeyFormat' => $blockField['translationKeyFormat'],
                    'typesettings' => Json::decode(Json::encode($blockField['settings'])),
                ];

                if ($blockField::class == Matrix::class) {
                    $fields['new' . $j]['typesettings']['blockTypes'] = $this->processCloneMatrix($blockField);
                }
            }

            $blockTypes['new' . $i] = [
                'fields' => $fields,
            ];
        }

        return $blockTypes;
    }

    public function createFieldLayoutFromConfig(array $config): FieldLayout
    {
        $layout = FieldLayout::createFromConfig($config);
        $layout->type = Block::class;

        return $layout;
    }
}
