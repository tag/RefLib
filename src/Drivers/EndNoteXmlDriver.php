<?php

namespace RefLib\Drivers;
use RefLib;

/**
* EndNote XML driver for RefLib
*/
class EndNoteXmlDriver extends AbstractDriver
{
    var $driverName = 'EndNoteXML';

    /**
    * The internal name to call the file
    * As far as I am aware this does not actually serve a purpose but EndNote refuses to import the file unless its specified
    * @var string
    */
    var $endNoteFile = 'EndNote.enl';

    /**
     *  @var array Maps (EndNoteXmlKey)=>(RefLibKey)
     */
    protected $map = array( // TODO: Some modification needed for use in export
        'titles/title' => 'title',
        'titles/secondary-title' => 'title-secondary',
        'titles/short-title'     => 'title-short',
        'periodical/full-title'  => 'periodical-title',
        'dates/year' => 'year',
        'access-date' => 'access-date',
        'auth-address' => 'address',
        'pages' => 'pages',
        'volume' => 'volume',
        'number' => 'number',
        'section' => 'section',
        'abstract' => 'abstract',
        'isbn' => 'isbn',
        'notes' => 'notes',
        'research-notes' => 'research-notes',
        'label' => 'label',
        'caption' => 'caption',
        'language' => 'language',
        'custom1' => 'custom1',
        'custom2' => 'custom2',
        'custom3' => 'custom3',
        'custom4' => 'custom4',
        'custom5' => 'custom5',
        'custom6' => 'custom6',
        'custom7' => 'custom7'
    );

    /**
    * Escpe a string in an EndNote compatible way
    * @param string $string The string to be escaped
    * @return string The escaped string
    */
    protected function escape($string) {
        return htmlentities($string, ENT_XML1, 'UTF-8');
    }

    /**
    * Computes the default filename if given a $salt
    * @param string $salt The basic part of the filename to use
    * @return string The filename including extension to use as default
    */
    function getFileName($salt = 'EndNote') {
        return "{$salt}.xml";
    }

    /**
    * Return the raw XML of the $refs array
    * @see $refs
    */
    function export(Array $refs) {
        if ($refs instanceof Reference) {
            $refs = [$refs];
        }

        $out = '<' . '?xml version="1.0" encoding="UTF-8"?' . '><xml><records>';
        $number = 0;
        foreach ($refs as $id => $ref) {
            $out .= '<record>';
            $out .= '<database name="' . $this->endNoteFile . '" path="C:\\' . $this->endNoteFile . '">' . $this->escape($this->endNoteFile) . '</database>';
            $out .= '<source-app name="EndNote" version="16.0">EndNote</source-app>';
            $out .= '<rec-number>' . $number . '</rec-number>';
            $out .= '<foreign-keys><key app="EN" db-id="s55prpsswfsepue0xz25pxai2p909xtzszzv">' . $number . '</key></foreign-keys>';
            $out .= '<ref-type name="Journal Article">17</ref-type>';

            $out .= '<contributors><authors>';
            foreach ($ref['authors'] as $author) {
                $out .= '<author><style face="normal" font="default" size="100%">';
                $out .= $this->escape($author) . '</style></author>';
            }
            $out .= '</authors></contributors>';

            $out .= '<titles>';
            $out .= '<title><style face="normal" font="default" size="100%">' . $this->escape($ref['title']) . '</style></title>';
            $out .= '<secondary-title><style face="normal" font="default" size="100%">';
            if (isset($ref['title-secondary']) && $ref['title-secondary']) {
                $out .= $this->escape($ref['title-secondary']);
            }
            $out .= '</style></secondary-title>';
            if (isset($ref['title-short']) && $ref['title-short']) {
                $out .= '<short-title><style face="normal" font="default" size="100%">';
                $out .= $this->escape($ref['title-short']) . '</style></short-title>';
            }
            $out .= '</titles>';

            $out .= '<periodical><full-title><style face="normal" font="default" size="100%">';
            if (isset($ref['periodical-title']) && $ref['periodical-title']) {
                $out .= $this->escape($ref['periodical-title']);
            }
            $out .= '</style></full-title></periodical>';

            // Simple key values
            foreach ($this->map as $enkey => $ourkey) {
                if (isset($ref[$ourkey]) && $ref[$ourkey]) {
                    $out .= "<$enkey><style face=\"normal\" font=\"default\" size=\"100%\">";
                    $out .= $this->escape($ref[$ourkey]) . "</style></$enkey>";
                }
            }
            $out .= '<dates>';
            $out .= '<year><style face="normal" font="default" size="100%">';
            if (isset($ref['year']) && $ref['year']) {
                $out .= $this->escape($ref['year']);
            }
            $out .= '</style></year>';
            $out .= '<pub-dates><date><style face="normal" font="default" size="100%">';
            if (isset($ref['date']) && $ref['date']) {
                $out .= $this->escape($this->parent->toDate($ref['date']));
            }
            $out .= '</style></date></pub-dates>';
            $out .= '</dates>';

            if (isset($ref['urls']) && $ref['urls']) {
                $out .= '<urls><related-urls>';
                foreach ((array) $ref['urls'] as $url) {
                    $out .= '<url><style face="normal" font="default" size="100%">' . $this->escape($url) . '</style></url>';
                }
                $out .= '</related-urls></urls>';
            }

            $out .= '</record>';
            $number++;
        }
        $out .= '</records></xml>';
        return $out;
    }

    /**
    * Return the text content of a SimpleXMLElement
    * @param SimpleXMLELement $xmlnode The node to return the content of
    * @return string The content of $xmlnode
    * @access protected
    */
    protected function getText($xmlnode) {
        return (string) $xmlnode[0][0];
    }

    function import($xml) {
        $dom = new \SimpleXMLElement($xml);
        $imported = [];
        foreach ($dom->records->record as $record) {
            $ref = new RefLib\Reference();

            foreach ($record->xpath('contributors/authors/author/style/text()') as $authors) 
                $ref['authors'][] = $this->getText($authors);

            foreach ($record->xpath('urls/related-urls/url/style/text()') as $url) 
                $ref['urls'][] = $this->getText($url);

            if ($find = $record->xpath("dates/pub-dates/date/style/text()"))
                $ref['date'] = RefLib\RefLib::toEpoc($this->getText($find), $ref);

            // Simple key=>vals
            foreach ($this->map as $enkey => $ourkey) {
                if (! $find = $record->xpath("$enkey/style/text()") )
                    continue;
                $ref[$ourkey] = $this->getText($find);
            }
            $ref = RefLib\RefLib::applyFixes($ref);

            $imported[] = $ref;
            //TODO: Solve refId problem later. Make a property of the Reference
            // if (!$this->parent->refId) { // Use indexed array
            //     $this->parent->refs[] = $ref;
            // } elseif (is_string($this->parent->refId)) { // Use assoc array
            //     if ($this->parent->refId == 'rec-number') {
            //         // Stupidly convert the XML object into an array - wish there were some easier way to do this but xPath doesnt seem to watch to match 'rec-number/text()'
            //         $recArr = (array) $record;
            //         $recno = (int) $recArr['rec-number'];
            //         if (!$recno) {
            //             trigger_error('No record number to associate reference to');
            //             $this->parent->refs[$ref[$this->parent->refId]] = $ref;
            //         } else {
            //             $this->parent->refs[$recno] = $ref;
            //         }
            //     } elseif (!isset($ref[$this->parent->refId])) {
            //         trigger_error("No ID found in reference to use as key");
            //     } else {
            //         $this->parent->refs[$ref[$this->parent->refId]] = $ref;
            //     }
            // }
        }
        // if ($this->parent->refId == 'rec-number') // Resort by keys so that everything is in order
        //     ksort($this->parent->refs);
        return $imported;
    }
}
