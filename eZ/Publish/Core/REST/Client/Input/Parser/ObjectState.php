<?php

/**
 * File containing the ObjectState parser class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */

namespace eZ\Publish\Core\REST\Client\Input\Parser;

use eZ\Publish\Core\REST\Common\Input\BaseParser;
use eZ\Publish\Core\REST\Common\Input\ParsingDispatcher;
use eZ\Publish\Core\REST\Common\Input\ParserTools;
use eZ\Publish\Core\Repository\Values\ObjectState\ObjectState as CoreObjectState;

/**
 * Parser for ObjectState.
 */
class ObjectState extends BaseParser
{
    /** @var \eZ\Publish\Core\REST\Common\Input\ParserTools */
    protected $parserTools;

    public function __construct(ParserTools $parserTools)
    {
        $this->parserTools = $parserTools;
    }

    /**
     * Parse input structure.
     *
     * @param array $data
     * @param \eZ\Publish\Core\REST\Common\Input\ParsingDispatcher $parsingDispatcher
     *
     * @todo Error handling
     *
     * @return \eZ\Publish\API\Repository\Values\ObjectState\ObjectState
     */
    public function parse(array $data, ParsingDispatcher $parsingDispatcher)
    {
        $names = $this->parserTools->parseTranslatableList($data['names']);

        $descriptions = isset($data['descriptions'])
            ? $this->parserTools->parseTranslatableList($data['descriptions'])
            : array();

        return new CoreObjectState(
            array(
                'id' => $data['_href'],
                'identifier' => $data['identifier'],
                'priority' => (int)$data['priority'],
                'mainLanguageCode' => $data['defaultLanguageCode'],
                'languageCodes' => explode(',', $data['languageCodes']),
                'names' => $names,
                'descriptions' => $descriptions,
            )
        );
    }
}
