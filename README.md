Backward Compatibility and Roadmap
=============================
This fork breaks backward compatibility in several ways, detailed below. Further breaks are forthcoming, iminently, including driver renaming, and decoupling of driver and library.

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

First, all classes have been namespaced with the `RefLib` namespace, as part of implementing [PSR-1][PSR-1] standards. Examples in the docs have been changed too. In the future, driver class names will be changed, and possibly sub-namespaced.

Second, method and property names may have had case and/or visibilty changes consistent with PSR-2. All object methods now begin with a lower case. Private attributes/methods were marked as protected instead. Protected methods are no longer prefixed with an underscore.

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

| Old Name             | New Name        |
| -------------------  | --------------  |
| `SetFileContent()`   | `importFile()`  |
| `SetFileContents()`  | `importFile()`  |
| `ReJoin()`           | `joinAuthors()` |

Third, all code now presumes a [PSR-4][PSR-4] autoloader, so both code and examples will eschew `requre` statements.

In the very near future, the `RefLib#refs` attribute will, instead of holding an array of references as key/value sets, contain an array of `Reference` objects. When implemented, `Reference` objects will implement interfaces to allow access to fields as if they were arrays, so this change should be transparent.

[PSR-1]: http://www.php-fig.org/psr/psr-1/
[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/