# Changelog

## 4.0.0 - 2024-05-13

### Changed
- Now requires PHP `8.2.0+`.
- Now requires Craft `5.0.0+`.

## 3.0.8 - 2023-10-25

### Fixed
- Fix an error when viewing the audit screen.
- Fix lack of validation checks when creating or editing fields.

## 3.0.7 - 2023-07-11

### Added
- Add better guards for Super Table and Neo fields, when plugin not installed.

### Fixed
- Fix an issue where some fields in Matrix and Super Table fields weren’t having their settings created correctly.

## 3.0.6 - 2023-05-27

### Changed
- Only admins are now allowed to access plugin settings.

### Fixed
- Fix Neo groups being moved to clone field instead of cloned. (thanks @ttempleton).
- Export/clone new Neo properties up to 3.6.0. (thanks @ttempleton).

## 3.0.5 - 2023-01-05

### Fixed
- Fix Audit items showing field layouts with no fields.
- Fix an error when running Audit on elements where their plugin is disabled, but installed.
- Fix error when importing Neo block type with null field layout. (thanks @ttempleton).

## 3.0.4 - 2022-10-13

### Fixed
- Fix an error when exporting Super Table fields.

## 3.0.3 - 2022-10-12

### Fixed
- Fix an error when cloning Neo fields.
- Fix an error when creating new fields.

## 3.0.2 - 2022-08-09

### Fixed
- Fix an error when cloning Neo fields.

## 3.0.1 - 2022-07-27

### Added
- Add English translations.

### Changed
- Replace deprecated `Craft.postActionRequest()` for JS.

### Fixed
- Fix an error when cloning a Matrix and Super Table combination.

## 3.0.0 - 2022-07-10

### Added
- Add checks for registering events for performance.

### Changed
- Now requires PHP `8.0.2+`.
- Now requires Craft `4.0.0+`.
- Rename base plugin methods.

## 2.2.5 - 2021-07-10

### Changed
- Revamp Neo import/export handling to support field layout elements.

### Fixed
- Improve `displayName` twig function.
- Fix an error when exporting Neo fields with child blocks configured.

## 2.2.4 - 2021-06-30

### Fixed
- Fix cloning and exporting Neo fields. Ensure you also upgrade to [Neo 2.9.12](https://github.com/spicywebau/craft-neo/releases/tag/2.9.12).

## 2.2.3 - 2021-05-30

### Fixed
- Fix incorrectly labelled “Save” button for importing fields.

## 2.2.2 - 2021-01-05

### Fixed
- Fix error when cloning or importing Neo fields.

## 2.2.1 - 2020-09-28

### Fixed
- Fix error when cloning Matrix fields. (thanks @brimby).

## 2.2.0 - 2020-09-05

### Changed
- Editing the name of an existing field no longer re-generates the field handle.
- Now required Craft 3.5+.

### Fixed
- Fix field widths not being retained when cloning Matrix fields.
- Fix error when cloning Matrix + Super Table fields.
- Add width property to import for Matrix.
- Add width property to export for Matrix.

## 2.1.8 - 2020-07-26

### Fixed
- Fix error when saving a Table field with a dropdown column option.

## 2.1.7 - 2020-07-23

### Fixed
- Fix being unable to clone Super Table field that has Matrix field.

## 2.1.6 - 2020-05-13

### Fixed
- Fix being unable to clone Matrix/Super Table/Neo fields correctly.
- Fix `searchable` attribute on fields not cloning, importing or exporting.

## 2.1.5 - 2020-04-18

### Fixed
- Fix missing field attributes when editing a field.

## 2.1.4 - 2020-04-18

### Fixed
- Fix error thrown when trying to resolve verbb-base resources.

## 2.1.3 - 2020-04-16

### Fixed
- Fix logging error `Call to undefined method setFileLogging()`.

## 2.1.2 - 2020-04-15

### Added
- Craft 3.4 compatibility.

### Changed
- File logging now checks if the overall Craft app uses file logging.
- Log files now only include `GET` and `POST` additional variables.

## 2.1.1 - 2019-11-27

### Added
- Allow selected field group to be remembered on page-loads.
- Update “New Field” button to populate the group based on the sidebar selection.

## 2.1.0 - 2019-05-11

### Added
- Add Neo support for cloning, importing and exporting.
- Add support for picking blocks and block fields for Super Table, Matrix and Neo fields when importing. This means you can choose specific fields or entire blocktypes to import, rename or exclude.
- Add field audit functionality. See what elements are using what fields, and also if there are any orphaned field layouts (field layouts that belong to an element group no longer there).
- Add translatable column to field index.
- Add option to download export JSON, or view output.

### Changed
- Now requires Craft 3.1.x.

### Fixed
- Enforce plugin to be hidden when `allowAdminChanges` is set.
- Fix some missing translations.

## 2.0.6 - 2019-05-11

### Fixed
- Fix cloning Matrix and Super Table fields, where fields weren’t getting their blocks and blocktypes correctly cloned.

## 2.0.5 - 2019-02-10

### Fixed
- Neo fields no longer clone (not supported yet).
- Fix Matrix/Super Table blocktypes not creating new field layouts.

## 2.0.4 - 2018-10-24

### Fixed
- Fix errors thrown when creating a new field

## 2.0.3 - 2018-02-25

### Fixed
- Fix Super Table parsing causing errors
- Updated field modal with the latest changes from Craft base. Helps with translation/site and field changes.

## 2.0.2 - 2018-02-25

### Changed
- Improve import support for `rias\positionfieldtype\fields\Position`
- Improve SuperTable/Matrix validation for import
- Improve Craft 2 migration of importing (caused fatal errors in some cases)
- Set minimum requirement to `^3.0.0-RC11`

## 2.0.1 - 2018-02-12

### Fixed
- Fix plugin icon in some circumstances

## 2.0.0 - 2017-12-12

### Added
- Craft 3 initial release.

## 1.5.5 - 2017-11-04

### Fixed
- Minor fix for sidebar icon.
- Fixed import issue where last group dropdown wasn't being set

## 1.5.4 - 2017-10-17

### Added
- Verbb marketing (new plugin icon, readme, etc).

### Fixed
- Fix for handle generation overriding import handle changes

## 1.5.3 - 2016-09-04

### Added
- Added import support for Smart Map, which needs a special-case.

## 1.5.2 - 2016-07-30

### Changed
- Now checks for installed fieldtypes on import/clone.
- Added auto-handle generation on mapping screen when importing.
- Now uses `fieldType->prepSettings` when cloning fields. Ensures fields are setup correctly when cloning.

### Fixed
- Fixed issue with Position Select not importing properly
- Fixed issue with Matrix / Super Table fields moving fields to new cloned fields

## 1.5.1 - 2016-07-15

### Fixed
- Fixed issue with Matrix/Super Table cloning not applying changes made on the edit screen.

## 1.5.0 - 2016-06-14

### Added
- Added clone, import/export support for Matrix > SuperTable > Matrix field configuration (phew!).

### Changed
- Changed export/import JSON structure for better consistency, readability and for Matrix/SuperTable support. Supports older exports with deprecation notice.
- Allow `metaKey` (cmd+click) functionality. Thanks to [@timkelty](https://github.com/timkelty)
- Refactored cloning logic for all fields (now much simpler)
- Better logging and UI feedback for various tasks
- Better clone, import/export support for Matrix-SuperTable combination
- Better logging and flow for import - doesn't die so much when things go wrong

### Fixed
- Fix issue for Matrix and SuperTable fields not being created properly on import/clone.
- Fix where field modals disappeared when failing validation.
- Fix for reordering dropdown fields (and other option fields) and not persisting order.
- Fix field options not updating when changing field type.

## 1.4.5 - 2016-03-15

### Fixed
- Ensure database handling methods take into account custom database names. Thanks to [@tcsehv](https://github.com/tcsehv).

## 1.4.4 - 2016-03-13

### Fixed
- Normalise all modal save/cancel/clone event handlers.

## 1.4.3 - 2016-02-21

### Added
- Create new fields directly from the Field Manager page [#16](https://github.com/verbb/field-manager/issues/16).
- Fields are shown if they are unused - handy to keep your fields clean [#16](https://github.com/verbb/field-manager/issues/16).

### Fixed
- Fixed issue when cloning Matrix field and not carrying over instructions [#17](https://github.com/verbb/field-manager/issues/17).
- Instructions were missing when importing/exporting Matrix & Super Table field.
- Additional fixes for Matrix/Super Table nested cloning and importing.

## 1.4.2 - 2016-01-13

### Fixed
- Fixed issue with plugin release feed url.

## 1.4.1 - 2015-12-27

### Fixed
- Better support for SuperTable cloning [#13](https://github.com/verbb/field-manager/issues/13).
- Fixed issue with Matrix and SuperTable fields not properly setting their content columns after import.

## 1.4.0 - 2015-12-02

### Added
- Craft 2.5 support, including release feed and icons.

## 1.3.5 - 2015-12-02

### Fixed
- Fixed import/export issue with Position Select field - [#9](https://github.com/verbb/field-manager/issues/9).

## 1.3.4 - 2015-12-02

### Changed
- Fully support import/export Matrix-in-SuperTable and SuperTable-in-Matrix.
- Refactor import/export services.

## 1.3.3 - 2015-12-02

### Added
- Added support for [SuperTable](https://github.com/engram-design/SuperTable).

### Fixed
- Fix for 'Show in CP Nav' setting - [#5](https://github.com/verbb/field-manager/issues/5).
- Fix when exporting Matrix fields, field settings weren't getting exported - [#6](https://github.com/verbb/field-manager/issues/6).
- Minor cosmetic fixes.

## 1.3.2 - 2015-12-02

### Added
- Added support for exporting Matrix fields.

## 1.3.1 - 2015-12-02

### Added
- Added full-featured import mapping. Allows changing of field name, handle and group assignment for each field for import.

## 1.3.0 - 2015-12-02

### Added
- Added import/exporting of fields.

## 1.2.0 - 2015-12-02

### Changed
- Edit fields or field groups directly from the FieldManager screen. Just click the blue links on the left-hand side of the table.
- Better error-handling for `saveField()`
- Swapped HUD for Modal when cloning single field. Allows editing of all field settings/properties, not just group, name and handle.

## 1.1.0 - 2015-12-02

### Added
- Added an option to provide a `prefix` to be used for all fields handle value when cloning group. This is because field handles need to be unique!

## 1.0.0 - 2015-12-02

- Initial release.
