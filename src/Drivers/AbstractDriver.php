<?php

namespace RefLib\Drivers;

/**
* Driver parent class for RefLib
*/
abstract class AbstractDriver
{
    /**
     *
     * @param string $contents The string (e.g., file contents) to import
     * @return array An array of References
     **/
    abstract public function import($contents);


    /**
    * Attempts to understand the divider between author fields and returns back 
    * the field in "{$author1}{$outseperator}{$author2}" format
    * @param string $authors The incomming author field to process
    * @param array|string $seperators An array of seperators to try, if none specified 
    *        a series of internal seperators is used; if a string, only that 
    *        seperator will be used
    * @param string $outseperator The output seperator to use
    * @return string The supporte author field
    * @todo //TODO: split into implode, explode functions?
    */

    public static function joinAuthors($authors, $seperators = null, $outseperator = ' AND ')
    {
        if (!$seperators) {
            $seperators = array(', ', '; ', ' AND ');
        }

        foreach ((array) $seperators as $seperator) {
            $bits = explode($seperator, $authors);
            if (count($bits) > 1) {
                return implode($outseperator, $bits);
            }
        }

        return $authors;
    }
    // }}}
}
