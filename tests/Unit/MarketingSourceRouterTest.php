<?php

namespace Tests\Unit;

use App\Support\Marketing\MarketingSourceRouter;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class MarketingSourceRouterTest extends TestCase
{
    public function test_it_detects_google_ads_and_keeps_click_id_for_checkout(): void
    {
        $router = new MarketingSourceRouter;
        $request = $this->request(
            'https://dommood.com.ua/catalog?gclid=test-gclid&utm_source=google&utm_medium=cpc&utm_campaign=pmax'
        );

        $attribution = $router->capture($request);

        $this->assertSame('google', $attribution['source']);
        $this->assertSame('google_ads', $attribution['channel']);
        $this->assertSame('test-gclid', $attribution['click_ids']['gclid'] ?? null);
        $this->assertSame('google', $attribution['utm']['utm_source'] ?? null);
    }

    public function test_it_clears_old_click_ids_when_paid_source_changes(): void
    {
        $router = new MarketingSourceRouter;
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();

        $router->capture($this->request(
            'https://dommood.com.ua/catalog?gclid=old-google&utm_source=google&utm_medium=cpc',
            session: $session
        ));

        $attribution = $router->capture($this->request(
            'https://dommood.com.ua/catalog?fbclid=new-meta',
            'https://facebook.com/',
            $session
        ));

        $this->assertSame('meta', $attribution['source']);
        $this->assertSame('meta_ads', $attribution['channel']);
        $this->assertSame('new-meta', $attribution['click_ids']['fbclid'] ?? null);
        $this->assertArrayNotHasKey('gclid', $attribution['click_ids']);
    }

    private function request(string $url, ?string $referer = null, ?Store $session = null): Request
    {
        $server = [];

        if ($referer) {
            $server['HTTP_REFERER'] = $referer;
        }

        $request = Request::create($url, 'GET', [], [], [], $server);
        $session ??= new Store('test', new ArraySessionHandler(120));

        if (! $session->isStarted()) {
            $session->start();
        }

        $request->setLaravelSession($session);

        return $request;
    }
}
