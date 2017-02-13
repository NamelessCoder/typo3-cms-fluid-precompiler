TYPO3 CMS Fluid Template Precompiler
====================================

Command line tool which scans your TYPO3 site for and compiles without rendering, Fluid template files. Simply put,
warms up the Fluid template cache by parsing and compiling all templates without rendering them.

Also functions as a basic linting utility - any errors automatically cause the template to be uncompilable and the
parsing-specific extension message such as "undeclared arguments passed to ViewHelper" is presented as failure message.


Installation
------------

Only available through composer:

    composer require namelesscoder/typo3-cms-fluid-precompiler


Purpose
-------

The purpose of this utility is to allow Fluid templates to be compiled ("cached") without having to render each one, as
is normally required before the Fluid template gets compiled.

The intention is to enable:

* Development / continuous integration (optional failure exit code) and reporting of uncompilable templates.
* Fairly verbose output containing mitigation suggestions when a template is uncompilable.
* Ability to "warm up" all Fluid templates' compiled states as part of a deployment script.

The utility exclusively supports pre-compiling of templates which conform to the TYPO3 template folder naming
conventions - that means that only templates which exist in the *default paths of each extension* will be compiled.
And this means templates are expected in `EXT:$extkey/Resources/Private/Templates`, layouts are expected in
`EXT:$extkey/Resources/Private/Layouts` and partials in `EXT:$extkey/Resources/Private/Partials`. Any template file not
located in those paths will be ignored.


Usage
-----

All parameters can be combined as needed and all parameters have a short name like `-h` and a long name like `--help`.
Only one of the parameters - `-e` or `--extension` - takes an argument value.

### Print this help text

    ./vendor/bin/fluid-precompile -h
    ./vendor/bin/fluid-precompile --help

### Pre-compile every template in every installed extension.

    ./vendor/bin/fluid-precompile

### Pre-compile every template in a specific, installed extension.

    ./vendor/bin/fluid-precompile -e my_extension
    ./vendor/bin/fluid-precompile --extension my_extension

### Silence output

    ./vendor/bin/fluid-precompile -s
    ./vendor/bin/fluid-precompile --silent

### Verbose output

    ./vendor/bin/fluid-precompile -v
    ./vendor/bin/fluid-precompile --verbose

### Fail (exit code 1) on any uncompilable template

    ./vendor/bin/fluid-precompile -f
    ./vendor/bin/fluid-precompile --fail


CI Usage
--------

The tool can also be used with Composer's `--require-dev` and called from a continuous integration framework, in case
you want to prevent any uncompilable or otherwise error prone templates from being committed to your project.

I recommend the following command to perform the check:

    ./vendor/bin/fluid-precompile -e $EXTKEY -v -f

Which will attempt to compile all templates in extension `$EXTKEY` (which you must define/substitute, of course) and
be verbose about the output (to include any failure messages and print all status for all templates) and finally to fail
if any uncompilable templates are detected (but still report all failures if there are more than one).

Bonus effect: this performs basic linting on your Fluid template files to ensure that they can be parsed correctly.
Note that this doesn't necessarily mean that your templates also *render* correctly; the linting does not take things
like TypoScript variables or potentially missing sections/partials into account, it only validates the syntax you used.


A word on custom implementations
--------------------------------

It is perfectly possible to implement your own template pre-compiling logic - and do so very quickly. If you look into
the source code of the `FluidPrecompiler` class shipped with this utility you can see that the requirements are
excessively simple:

* You need the TYPO3 context, to include things like the cache definitions (a custom bootstrapping application class
  is used to do that - if you do this from within an existing TYPO3 context no such thing is necessary).
* You need to build an instance of a RenderingContext which is specific to one extension - once you have the instance
  you can then override settings to provide different template paths.
* The pre-compiling (in Fluid terms, warmup) result contains information about all detected files with their individual
  status and any failure reasons if the template was uncompilable.

This makes it fairly easy to implement a custom warmup feature in for example a TYPO3 backend module, as hook that
triggers after caches are flushed, as part of your extension installation process, and so on.

When you encounter cases which appear to require a custom implementation, consider the following:

* Are you breaking MVC in your template file naming - would it be better to conform to MVC?
* Would it be better to place your Fluid template files in the default locations as per convention?
* Is your rendering setup perhaps too complex - for example, does it demand a lot of custom View initialisation?
* Are you placing your template files in `fileadmin`? Then urgently consider putting them in an extension.

