<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftunit\mail;

use Codeception\Test\Unit;
use Craft;
use craft\helpers\App;
use craft\mail\Mailer;
use craft\mail\Message;
use craft\mail\transportadapters\Sendmail;
use craft\models\MailSettings;
use yii\base\InvalidConfigException;

/**
 * Unit tests for MailerTest
 *
 * Currently getMailer returns the TestMailer class (See line 264 of Codeception\Lib\Connector\Yii2) and not a craft\mail\Mailer object.
 * We need a way to test lines 89-167 of the craft\mail\Mailer object which is currently awkward.
 *
 * One other option is to break out craft\mail\Mailer line 89-165 into a separate method called prepareMessage(Message $message)
 * This means we can test all that functionality without having to actually *send* the email.
 *
 * @todo Are we going to override the yii\mail\BaseMailer\TestMailer class in the Craft CMS module. So that we can
 * mock Craft::$app->getMailer()->send();
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.1
 */
class MailerTest extends Unit
{
    // Public Properties
    // =========================================================================

    /**
     * @var Mailer $mailer
     */
    public $mailer;

    // Public Methods
    // =========================================================================

    // Tests Methods
    // =========================================================================

    /**
     * Tests mail from key composition
     *
     * @dataProvider fromKeyCompositionDataProvider
     *
     * @param $key
     * @param array $variables
     * @throws InvalidConfigException
     */
    public function testFromKeyComposition($key, array $variables = [])
    {
        $res = $this->mailer->composeFromKey($key, $variables);
        $this->assertInstanceOf(Message::class, $res);
        $this->assertSame($key, $res->key);
        $this->assertSame($variables, $res->variables);
    }

    // Data Providers
    // =========================================================================

    /**
     * @return array
     */
    public function fromKeyCompositionDataProvider(): array
    {
        return[
            ['account_activation', []],
            ['not_a_key that exists']
        ];
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function _before()
    {
        parent::_before();

        // TODO: This used to work without the config array.
        // The introduction of project config throws: [TypeError] Argument 1 passed to craft\helpers\MailerHelper::createTransportAdapter() must be of the type string, null given, called in /home/cms/src/helpers/App.php on line 446
        // So. If project config is off, and no MailSettings is passed, App::mailerConfig doesnt seem to work.
        $mailSettings = new MailSettings([
            'transportType' => Sendmail::class
        ]);

        $this->mailer = Craft::createObject(App::mailerConfig(
            $mailSettings
        ));
    }
}
