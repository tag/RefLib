Backward Compatibility and Roadmap
=============================
This fork [breaks backward compatibility](#backward-compatibility-breaks) in several ways, detailed below. Further breaks are forthcoming, iminently, including driver renaming, and decoupling of driver and library.

RefLib
======
PHP module for managing a variety of citation reference libraries.

At present this library can read/write the following citation library formats:

* EndNote (XML)
* [RIS](https://en.wikipedia.org/wiki/RIS_(file_format))
* CSV files


Installation
------------
The easiest way to install is via Composer - `composer require hashbang/reflib`

If you wish to install *without* composer then download the source code, unzip it into a directory include the file in the normal way.


Examples
========

Read in EndNote XML
-------------------

	$lib = new RefLib\RefLib();
	$lib->importFile('tests/data/endnote.xml');

	print_r($lib->refs); // Outputs all processed refs in an associative array


Write EndNote XML
-----------------

	$lib = new RefLib\RefLib();
	$lib->importFile('tests/data/endnote.xml'); // Read in content (or populate $lib->refs yourself)
	$lib->OutputXML('EndNote File.xml'); // Output file to the browser


File conversion
---------------

	$lib = new RefLib\RefLib();
	$lib->importFile('tests/data/endnote.xml'); // Read in content (or populate $lib->refs yourself)
	$lib->OutputXML('EndNote File.ris'); // Output file to the browser in RIS format

Backward Compatibility Breaks
=============================
This fork breaks backward compatibility in several ways.

1. All classes have been namespaced with the `RefLib` namespace, as part of implementing [PSR-1][PSR-1] and [PSR-4][PSR-4] standards. Examples in the docs have been changed too. In the future, driver class names will be changed, and possibly sub-namespaced.

2. method and property names may have had case and/or visibilty changes consistent with [PSR-2][PSR-2]. All object methods now begin with a lower case. Private attributes/methods were marked as protected instead. Protected methods are no longer prefixed with an underscore.

   ```php
   // EXAMPLE

   // Previously:
   $lib->Add($ref);
   $lib->_SlurpPeek($file, $lines);

   // Now:
   $lib->add($ref);
   $lib->slurpPeek($file, $lines);  // Error: Protected
   ```

  In the process, some method names have been changed to enhance clarity. `Get*` and `Set*` method names have been replaces with `export*` and `import*` names, respectively. For example, instead of `RefLib#SetFileContents()`, now use `RefLib#importFile()`. A full map of name changes is shown in the table below:

  | Old Name             | New Name        | Notes |
  | -------------------  | --------------  | ----- |
  | `SetFileContent()`   | `importFile()`  | |
  | `SetFileContents()`  | `importFile()`  | |
  | `GetContents()`      | `export()`      | Uses default driver; error if not set |
  | `ReJoin()`           | `joinAuthors()` | |
  | `ApplyFixes()`       | `::applyFixes()`| Static, for now |
  | `FixPages()`         | `::fixPages()`  | Static, for now |

  Some attributes changed too:

  | Old Name             | New Name        | Notes |
  | -------------------  | --------------  | ----- |
  | `driver`             | `defaultDriver` | See new documentation on Drivers |
  | `fixesBackup`        | `::$fixesBackup`   | Static, for now |
  | `applyFixPages`      | `::$applyFixPages` | Static, for now |

  Similar changes have been made to [Drivers](#drivers). See that section for more details.

3. All code now presumes a [PSR-4][PSR-4] autoloader, so both code and examples will eschew `requre` statements.

4. Behavior of some (mostly internal) methods has changed. Specifically:
   * `#getDrivers` still returns an array, but the keys are different. See the new driver methods for details.

Roadmap
-------
In the very near future, the `RefLib#refs` attribute will, instead of holding an array of references as key/value sets, contain an array of `Reference` objects. When implemented, `Reference` objects will implement interfaces to allow access to fields as if they were arrays, so this change should be transparent.

[PSR-1]: http://www.php-fig.org/psr/psr-1/
[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/

Drivers
=======
Drivers are more powerful now. For example, `#importFile()` can accept an `AbstractDriver` as a third parameter; if none is supplied, it will attempt to autodetect (as previous).

As with `RefLib` main class, the drivers' `Get*` and `Set*` method names have been replaces with `export*` and `import*` names, respectively.

| Old Name             | New Name        | Notes |
| -------------------  | --------------  | ----- |
| `SetFileContent()`   | `importFile()`  | |
| `SetFileContents()`  | `importFile()`  | |
| `GetContents()`      | `export()`      | |

One major difference is that `import` returns an array of `Reference`, rather than adding them directly, so the calling context may be slightly more work.

```php
// Old way
$refLib->SetContents($fileContents);

// New way; either way works
$refLib->add($driver->import($fileContents));
$refLib->importFile($fileContents);
```
Note from the above example that `#add` now accepts a single reference or an array of references.
