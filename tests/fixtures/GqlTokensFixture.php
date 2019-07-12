<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace crafttests\fixtures;

use craft\records\GqlToken;
use craft\test\Fixture;

/**
 * Class GqlTokensFixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.3
 */
class GqlTokensFixture extends Fixture
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $modelClass = GqlToken::class;

    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/gql-tokens.php';
}