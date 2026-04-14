<?php

namespace App\Livewire\Content;

use App\Models\ProductListing;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProductEditor extends Component
{
    public string $siteId;

    public ?string $productId = null;

    public string $name = '';

    public string $description = '';

    public ?string $price = null;

    public string $currency = 'USD';

    public array $images = [];

    public string $imageInput = '';

    /** @var array<string, string> Custom key/value fields (not Livewire HTML attributes). */
    public array $productAttributes = [];

    public string $attrKey = '';

    public string $attrValue = '';

    public string $outputPath = '';

    public string $status = 'draft';

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'nullable|numeric|min:0',
        'currency' => 'required|string|size:3',
        'status' => 'required|in:draft,active,archived',
        'outputPath' => 'nullable|string|max:500',
    ];

    public function mount(): void
    {
        $resolvedSiteId = SiteAccess::findOrFail($this->siteId)->id;
        $this->siteId = $resolvedSiteId;

        if ($this->productId) {
            $product = ProductListing::query()
                ->whereKey($this->productId)
                ->where('site_id', $this->siteId)
                ->firstOrFail();
            $this->name = $product->name;
            $this->description = $product->description ?? '';
            $this->price = $product->price;
            $this->currency = $product->currency;
            $this->images = $product->images ?? [];
            $this->productAttributes = $product->attributes ?? [];
            $this->outputPath = $product->output_path ?? '';
            $this->status = $product->status;
        }
    }

    public function addImage(): void
    {
        $url = trim($this->imageInput);
        if ($url && ! in_array($url, $this->images)) {
            $this->images[] = $url;
        }
        $this->imageInput = '';
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function addAttribute(): void
    {
        $key = trim($this->attrKey);
        $value = trim($this->attrValue);

        if ($key && $value) {
            $this->productAttributes[$key] = $value;
        }

        $this->attrKey = '';
        $this->attrValue = '';
    }

    public function removeAttribute(string $key): void
    {
        unset($this->productAttributes[$key]);
    }

    public function save(): void
    {
        $this->validate();
        SiteAccess::findOrFail($this->siteId);

        $data = [
            'site_id' => $this->siteId,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => $this->price,
            'currency' => strtoupper($this->currency),
            'images' => $this->images,
            'attributes' => $this->productAttributes,
            'output_path' => $this->outputPath ?: null,
            'status' => $this->status,
        ];

        if ($this->productId) {
            $product = ProductListing::query()
                ->whereKey($this->productId)
                ->where('site_id', $this->siteId)
                ->firstOrFail();
            $product->update($data);
        } else {
            $product = ProductListing::create($data);
            $this->productId = $product->id;
        }

        session()->flash('success', $this->productId ? 'Product updated.' : 'Product created.');
    }

    public function render(): View
    {
        return view('livewire.content.product-editor');
    }
}
