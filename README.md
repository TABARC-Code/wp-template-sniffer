<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Template Sniffer

The part of WordPress that decides which template file to load is clever and quiet.  
The part that tells you when your theme is missing key templates or shipping dead ones does not exist.
,,,...
This plugin fills that gap, with blunt honesty.

It looks at the active theme and its parent (if any) and reports:

- Which core templates are present or missing  
- Which templates the child theme overrides in the parent  
- Which page templates exist but nobody uses  
- Which page templates are referenced by content but missing on disk  
- A rough inventory of miscellaneous PHP templates  
- A basic count of block templates so you remember they exist  

No file edits. No magic repair. Just visibility.

## What it checks

### 1. Core template coverage.

It checks for the usual suspects at the root of the theme:

- `index.php`  
- `front-page.php`  
- `home.php`  
- `single.php`  
- `page.php`  
- `archive.php`  
- `category.php`  
- `tag.php`  
- `author.php`  
- `date.php`  
- `search.php`  
- `404.php`  
- `attachment.php`  
- `taxonomy.php`  
- `singular.php`  

For each one you see:

- Present in child theme  
- Present in parent theme  
- Missing entirely  

Missing templates are not automatically a bug. They just mean WordPress falls back to more generic templates more often than you might think.

If `index.php` is missing everywhere, the theme is broken. That one is mandatory.

### 2. Child overrides and parent only templates

If you are using a child theme, the plugin shows:

- Templates that exist in both child and parent with the same relative path  
  - These are hard overrides. The parent versions never run.  

- Templates that only exist in the parent theme  
  - The child theme falls back to these.  ,

This is useful when you are trying to work out why editing a template in the parent does nothing, or why a file you created in the child is apparently ignored.,

### 3. Page templates

Page templates are the special templates you pick in the Page edit screen under Template.

The plugin shows:

- All page templates defined by the theme  
  - Name and relative file path  

- Templates that exist but no page currently uses  
  - These are prime candidates for removal if they just confuse editors  

- Templates that content references but do not exist on disk  
  - Those pages are silently falling back to the default template  

This is where you discover that someone deleted `templates/landing.php` but half the marketing pages still think they are using it.

### 4. Miscellaneous PHP templates

This is a polite way of saying:

> All the random PHP files living in your theme that are not obviously core templates.

You get lists of:

- Child theme miscellaneous files  
- Parent theme miscellaneous files  

Some of these will be:

- Partial templates  
- Template parts  
- Old experiments someone forgot to delete  

The plugin does not know which are safe to remove. It just reminds you they exist.

### 5. Block templates (light touch)

For block or hybrid themes it checks:

- `templates/*.html`  
- `parts/*.html`  

It reports how many block template files it sees and where they live.

It does not attempt to validate them or map them to specific routes. That is a bigger job and the point of this plugin is to stay narrowly focused.

## What it does not do

Important list.

It does not:

- Edit template files  
- Move files between child and parent themes  
- Generate missing templates  
- Validate the actual PHP code  
- Validate the actual HTML structure  
- Decide which files you should delete  

It is a scanner. You still have to make design and cleanup decisions yourself.

## Requirements

- WordPress 6.0 or newer  
- PHP 7.4 or newer  
- Theme using the standard template hierarchy  
- Admin level access  

Classic themes are fully supported. Block themes get light surface inspection so they do not feel left out.

## Installation

Clone or download the repository:

```bash
cd wp-content/plugins/
git clone https://github.com/TABARC-Code/wp-template-sniffer.git
Activate in the WordPress admin:

Go to Plugins

Activate WP Template Sniffer

Then open the tool:

Go to Tools

Click Template Sniffer

If you do not see it, you probably are not an administrator.

How to use this without breaking things
First visit
On the Template Sniffer screen:



# wp-template-sniffer
Audits the active theme for missing core templates, child overrides, unused page templates and general template hierarchy nonsense. 
