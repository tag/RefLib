<?php

namespace RefLib\Drivers;

use RefLib;

/**
* RIS driver for RefLib
*
* NOTE: This driver for RefLib has only limited support for ENW fields
*/
class EnwDriver extends AbstractDriver
{
    public $driverName = 'ENW';

    /**
    * The parent instance of the RefLib class
    * @var class
    */
    public $parent;

    /**
    * Simple key/val mappings
    * Each key is the RIS format name, each Val is the RifLib version
    * Place preferencial keys for output at the top if multiple incomming keys match
    * @var array
    */
    protected $mapHash = array(
        /*
        // %I  // Publisher
        */
        // 'CA' => 'caption',
        // 'J2' => 'title-secondary',
        // 'C1' => 'custom1',
        // 'C2' => 'custom2',
        // 'C3' => 'custom3',
        // 'C4' => 'custom4',
        // 'C5' => 'custom5',
        // 'C6' => 'custom6',
        // 'C7' => 'custom7',
        // 'C8' => 'custom8',
        // 'LA' => 'language',
        // 'LB' => 'label',
        // 'N1' => 'notes',
        // 'SE' => 'section',
        // 'SN' => 'isbn',

        '%T' => 'title',
        '%J' => 'periodical-title', // Journal
        '%V' => 'volume',
        '%N' => 'number', // Issue #
        '%X' => 'abstract',
        '%D' => 'year',
        '%P' => 'pages'
    );

    /**
    * Similar to $mapHash but this time each value is an array
    * Place preferencial keys for output at the top if multiple incomming keys match
    * @var array
    */
    protected $mapHashArray = array(
        // Prefered keys
        '%A' => 'authors'
        // 'DO' => 'urls',

        // Regular keys
        // 'UR' => 'urls',
    );

    /**
    * Escpe a string in an EndNote compatible way
    * @param string $string The string to be escaped
    * @return string The escaped string
    */
    protected function escape($string)
    {
        return strtr($string, array(
            "\r" => '\n',
        ));
    }

    /**
    * Computes the default filename if given a $salt
    * @param string $salt The basic part of the filename to use
    * @return string The filename including extension to use as default
    */
    public function getFileName($salt = 'ENW')
    {
        return "$salt.enw";
    }

    public function export()
    {
        throw new Exception('Not Impelemented');
    }

    public function import($blob)
    {
        if (!preg_match_all('!^%0\s+(.*?)(?:\n{2,}|\Z)!ms', $blob, $matches, PREG_SET_ORDER)) {
            // \Z is end of string, even in multi-line mode
            return;
        }

        $recno = 0;
        foreach ($matches as $match) {
            $recno++;
            $ref = new RefLib\Reference();
            $ref['type'] = strtolower($match[1]);

            $rawref = array();
            preg_match_all('!^(%[\S])\s+(.*)$!m', $match[0], $rawrefextracted, PREG_SET_ORDER);
            foreach ($rawrefextracted as $rawrefbit) {
                // key/val mappings
                if (isset($this->mapHash[$rawrefbit[1]])) {
                    $ref[$this->mapHash[$rawrefbit[1]]] = trim($rawrefbit[2]);
                    continue;
                }

                // key/val(array) mappings
                if (isset($this->mapHashArray[$rawrefbit[1]])) {
                    $ref[$this->mapHashArray[$rawrefbit[1]]][] = trim($rawrefbit[2]);
                    continue;
                }

                // unknowns go to $rawref to be handled later
                if (isset($rawref[$rawrefbit[1]])) {
                    if (!is_array($rawref[$rawrefbit[1]])) {
                        $rawref[$rawrefbit[1]] = array($rawref[$rawrefbit[1]]);
                    }
                    $rawref[$rawrefbit[1]][] = trim($rawrefbit[2]);
                } else {
                    $rawref[$rawrefbit[1]] = trim($rawrefbit[2]);
                }
            }

            // }}}
            // Pages {{{
            // if (isset($rawref['SP']) && isset($rawref['EP'])) {
            //     $ref['pages'] = "{$rawref['SP']}-{$rawref['EP']}";
            // } elseif (isset($rawref['SP']))
            //     $ref['pages'] = $rawref['SP'];
            // }}}

            // Dates {{{
            // if (isset($rawref['%D']))
            //     if (substr($rawref['PY'], 0, 10) == 'undefined/') {
            //         // Pass
            //     } elseif (preg_match('!([0-9]{4})///!', $rawref['%D'], $date)) { // Just year
            //         $ref['year'] = $date[1];
            //     } elseif (preg_match('!([0-9]{4})/([0-9]{1,2})//!', $rawref['PY'], $date)) { // Just month
            //         $ref['date'] = strtotime("{$date[1]}-{$date[2]}-01");
            //     } elseif (preg_match('!([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/!', $rawref['PY'], $date)) // Full date
            //         $ref['date'] = strtotime("{$date[1]}-{$date[2]}-{$date[1]}");

            // Append to $this->parent->refs {{{
            if (!$this->parent->refId) { // Use indexed array
                $this->parent->refs[] = $ref;
            } elseif (is_string($this->parent->refId)) { // Use assoc array
                if ($this->parent->refId == 'rec-number') {
                    $this->parent->$refs[$recno] = $ref;
                } elseif (!isset($ref[$this->parent->refId])) {
                    trigger_error("No ID found in reference to use as key");
                } else {
                    $this->parent->refs[$ref[$this->parent->refId]] = $ref;
                }
            }
            // }}}
        }
    }
}
