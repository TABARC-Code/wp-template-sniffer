## `IdiotsGuide.md`

```markdown
# IdiotsGuide  
WP Template Sniffer

This is for the version of me who vaguely remembers the phrase "template hierarchy" and nothing else, but needs to work out why the front end looks wrong.

No theory dump. Just enough context to use the tool without making things worse.

## The very short version of the template hierarchy

When someone hits a page on your site, WordPress goes looking for a template file to use.

Rough idea:

- It starts with the most specific file name it knows  
- If that does not exist, it falls back to something more generic  
- Eventually it ends at `index.php`  

For a single post, the chain might be:

- `single-post.php`  
- `single.php`  
- `singular.php`  
- `index.php`  

For a page it might be:

- A dedicated page template if one is assigned  
- `page-{slug}.php`  
- `page.php`  
- `singular.php`  
- `index.php`  

If files in those spots do not exist, WordPress quietly moves on. It never tells you what is missing.

That is where this plugin comes in.

## What Template Sniffer gives you

### Core template coverage

It shows you which of the usual core files exist:

- `index.php`  
- `single.php`  
- `page.php`  
- `archive.php`  
- `search.php`  
- And the rest of the usual suspects  

If `index.php` is missing, your theme is broken.  
If others are missing, the site might still work, but more logic is falling through to generic templates than you might expect.

### Child vs parent theme files

If you have a child theme:

- Anything in the child with the same path as the parent hides the parent version  
- Anything only in the parent is used when the child has nothing better  

The plugin shows:

- Which files are overrides  
- Which files only exist in the parent  

So when you edit `single.php` in the parent and nothing changes, you can confirm whether the child already overrides it.

### Page templates

These are the templates you pick per page in the editor.

The plugin shows:

- Templates that exist and how many are unused  
- Templates that content is trying to use but which do not exist on disk  

This is the difference between:

> "The page is using a custom template"

and

> "The page thinks it is using a template that was deleted three years ago so WordPress is quietly using the default instead".

## How to actually use this without reading a textbook

### Scenario 1  
You edit a template and nothing changes.

Steps:

1. Open Template Sniffer.  
2. Look at the child overrides list.  
3. If the file you edited is listed there, you probably edited the wrong copy.  
   - You changed the parent version  
   - The child version is the one that runs  

Fix by editing the child template or removing the override if you want the parent behaviour.

### Scenario 2  
Some pages look wrong. Others are fine.

Steps:

1. Edit a broken page. Check which template it is using.  
2. In Template Sniffer, look under page templates:  
   - If that template is in the "missing" list, WordPress is falling back to the default template.  
   - If it is in the "unused" list, something else is going on.  

If the template is missing, either:

- Restore the file  
- Assign a different template  
- Decide the page should use the default  

### Scenario 3  
The site seems to use the same layout for everything.

Steps:

1. Look at core template coverage.  
2. If `single.php`, `page.php`, `archive.php` and `search.php` are all missing, everything is falling back to `index.php`.  

That might be intentional for very minimal themes.  
It might also be someone being lazy in a hurry.

## Things to be careful about

This plugin is read only. The danger comes from what you do with the information.

A few rules to avoid hurting yourself:

- Do not delete template files just because they show up in "miscellaneous"  
- Do not rename template files without understanding the hierarchy  
- Do not remove page templates until you have checked nobody uses them  
- Do not copy files between child and parent without knowing which one WordPress prefers  

If you are unsure, test on a staging copy first.

## Mental shortcuts for tired brains

When you have no attention left:

- Red flag: missing `index.php`  
- Mild concern: missing `single.php` and `page.php` so everything relies on `index.php`  
- Confusion: template edit does nothing, probably an override in the child theme  
- Ghosts: pages using templates that are listed as "missing"  

Use Template Sniffer as the "what is really happening" view before you start hacking files.

## When to reach for this plugin

You do not need it daily.

Good times to use it:

- When you inherit a theme and want to know how bad it is  
- Before starting a redesign on top of an existing child theme  
- When moving from classic to block and trying to keep your sanity  
- When debugging layout issues that make no sense  

It is a map, not a magic wand.  
The map is still worth having.

## Final thought

WordPress will always try to render something, even when half the templates are missing.

Template Sniffer just points at the missing pieces and shrugs in your direction.

What you fix, and what you leave as is, is your call.
