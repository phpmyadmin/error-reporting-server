<?php

namespace App\Test\TestCase\Controller;

use App\Controller\PagesController;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\View\Exception\MissingViewException;

class PagesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Force the Pages controller to build a view class that does not exist.
     *
     * This is how the controller reaches its MissingViewException handling: a
     * missing template file throws a MissingTemplateException instead (see
     * testAMissingPageFileIsAnError()), which the controller does not catch.
     */
    protected function useAMissingViewClass(): void
    {
        EventManager::instance()->on('Controller.initialize', static function (EventInterface $event): void {
            $controller = $event->getSubject();
            if (! ($controller instanceof PagesController)) {
                return;
            }

            $controller->viewBuilder()->setClassName('MissingView');
        });
    }

    public function testHomePageIsDisplayed(): void
    {
        $this->get('/pages/home');

        $this->assertResponseOk();
        $this->assertSame('home', $this->viewVariable('page'));
        $this->assertNull($this->viewVariable('subpage'));
        $this->assertSame('Home', $this->viewVariable('title_for_layout'));
    }

    public function testNoPageRedirectsToTheHomePage(): void
    {
        $this->get('/pages');

        $this->assertResponseCode(302);
        $this->assertRedirect('/');
    }

    public function testMissingPageIsNotFound(): void
    {
        $this->useAMissingViewClass();

        $this->get('/pages/does-not-exist');

        $this->assertResponseCode(404);
    }

    public function testSubpageIsPassedToTheView(): void
    {
        Configure::write('debug', true);
        $this->useAMissingViewClass();
        $this->disableErrorHandlerMiddleware();

        try {
            $this->get('/pages/home/missing-subpage');
            $this->fail('A MissingViewException should have been thrown.');
        } catch (MissingViewException) {
            $this->assertSame('home', $this->viewVariable('page'));
            $this->assertSame('missing-subpage', $this->viewVariable('subpage'));
            $this->assertSame('Missing-subpage', $this->viewVariable('title_for_layout'));
        }
    }

    public function testAMissingPageFileIsNotFound(): void
    {
        // View::render() throws MissingTemplateException for a missing
        // template, PagesController answers with a 404 when debug is off.
        $this->get('/pages/does-not-exist');

        $this->assertResponseCode(404);
    }

    public function testAMissingPageFileIsRethrownInDebugMode(): void
    {
        Configure::write('debug', true);
        $this->get('/pages/does-not-exist');

        $this->assertResponseCode(500);
    }

    public function testMissingViewIsRethrownInDebugMode(): void
    {
        Configure::write('debug', true);
        $this->useAMissingViewClass();
        $this->disableErrorHandlerMiddleware();

        $this->expectException(MissingViewException::class);
        $this->get('/pages/does-not-exist');
    }
}
