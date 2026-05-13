<?php

namespace App\Http\Controllers;

use App\Services\ProductFeedService;
use Illuminate\Http\Response;

class FeedExportController extends Controller
{
    public function __construct(
        private readonly ProductFeedService $feedService
    ) {
    }

    public function googleMerchant(): Response
    {
        $xml = view('feeds.google_merchant', [
            'items' => $this->feedService->exportItems(ProductFeedService::CHANNEL_GOOGLE),
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function metaCatalog(): Response
    {
        return $this->csvResponse(
            $this->feedService->exportItems(ProductFeedService::CHANNEL_META),
            [
                'id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'sale_price',
                'link',
                'image_link',
                'additional_image_link',
                'brand',
                'item_group_id',
                'size',
                'color',
                'product_type',
                'google_product_category',
            ],
            'meta-catalog.csv'
        );
    }

    public function tiktokCatalog(): Response
    {
        return $this->csvResponse(
            $this->feedService->exportItems(ProductFeedService::CHANNEL_TIKTOK)
                ->map(fn (array $item): array => [
                    'sku_id' => $item['id'],
                    'item_group_id' => $item['item_group_id'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'availability' => $item['availability'],
                    'condition' => $item['condition'],
                    'price' => $item['price'],
                    'sale_price' => $item['sale_price'],
                    'link' => $item['link'],
                    'image_link' => $item['image_link'],
                    'brand' => $item['brand'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'product_type' => $item['product_type'],
                ]),
            [
                'sku_id',
                'item_group_id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'sale_price',
                'link',
                'image_link',
                'brand',
                'size',
                'color',
                'product_type',
            ],
            'tiktok-catalog.csv'
        );
    }

    protected function csvResponse($items, array $columns, string $filename): Response
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $columns);

        foreach ($items as $item) {
            $row = [];

            foreach ($columns as $column) {
                if ($column === 'price' || $column === 'sale_price') {
                    $value = $item[$column] ?? null;
                    $row[] = $value !== null ? number_format((float) $value, 2, '.', '').' UAH' : '';
                    continue;
                }

                if ($column === 'additional_image_link') {
                    $row[] = implode(',', $item['additional_image_links'] ?? []);
                    continue;
                }

                $row[] = $item[$column] ?? '';
            }

            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
