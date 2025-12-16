# Changelog

## 1.0.0.2c

- First public release.
- Added Tools  
  Template Sniffer screen.
- Theme overview:
  - Active theme and parent theme information.
  - Basic block template presence scan.
- Core template coverage:
  - Presence check for common hierarchy templates in child and parent.
  - Notes on missing or overridden templates.
- Child and parent comparison:
  - List of files where the child overrides the parent.
  - List of templates that exist only in the parent theme.
- Page templates:
  - List of page templates defined by the theme.
  - Detection of page templates that exist but are unused.
  - Detection of page template meta values used by content that do not exist on disk.
- Miscellaneous template listing:
  - Inventory of other PHP files under the theme directories.
- Read only:
  - No file edits or auto repairs.
- Licensed under GPL-3.0-or-later.
