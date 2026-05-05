<?php

use App\Models\BlogPost;
use App\Models\ContentTemplate;
use App\Models\ProductListing;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Content
Route::get('/sites/{site}/blog', function (Site $site) {
    $posts = $site->blogPosts()->latest()->get(['id', 'title', 'slug', 'status', 'published_at', 'created_at']);

    return view('dashboard.content.blog-index', ['site' => $site, 'posts' => $posts]);
})->name('blog.index');
Route::get('/sites/{site}/blog/create', fn (Site $site) => view('dashboard.content.blog-create', ['site' => $site]))->name('blog.create');
Route::get('/sites/{site}/blog/{blogPost}/edit', function (Site $site, BlogPost $blogPost) {
    abort_unless($blogPost->site_id === $site->id, 404);

    return view('dashboard.content.blog-edit', ['site' => $site, 'post' => $blogPost]);
})->name('blog.edit');
Route::post('/sites/{site}/blog', function (Request $request, Site $site) {
    $d = $request->validate(['title' => 'required|string|max:255', 'slug' => 'required|string|max:255', 'excerpt' => 'nullable|string', 'body' => 'nullable|string', 'status' => 'required|in:draft,published,scheduled', 'published_at' => 'nullable|date']);
    if ($d['status'] === 'scheduled') {
        $d['scheduled_at'] = $d['published_at'];
        $d['published_at'] = null;
    } elseif ($d['status'] === 'published' && empty($d['published_at'])) {
        $d['published_at'] = now();
    }
    $d['body'] = $d['body'] ?? '';
    $site->blogPosts()->create($d);

    return redirect("/dashboard/sites/{$site->id}/blog")->with('success', 'Post created.');
})->name('blog.store');
Route::put('/sites/{site}/blog/{blogPost}', function (Request $request, Site $site, BlogPost $blogPost) {
    abort_unless($blogPost->site_id === $site->id, 403);
    $d = $request->validate(['title' => 'required|string|max:255', 'slug' => 'required|string|max:255', 'excerpt' => 'nullable|string', 'body' => 'nullable|string', 'status' => 'required|in:draft,published,scheduled', 'published_at' => 'nullable|date']);
    if ($d['status'] === 'scheduled') {
        $d['scheduled_at'] = $d['published_at'];
        $d['published_at'] = null;
    } elseif ($d['status'] === 'published' && empty($d['published_at'])) {
        $d['published_at'] = now();
    }
    $d['body'] = $d['body'] ?? '';
    $blogPost->update($d);

    return back()->with('success', 'Post updated.');
})->name('blog.update');
Route::delete('/sites/{site}/blog/{blogPost}', function (Site $site, BlogPost $blogPost) {
    abort_unless($blogPost->site_id === $site->id, 403);
    $blogPost->delete();

    return back()->with('success', 'Post deleted.');
})->name('blog.destroy');
Route::get('/sites/{site}/products', function (Site $site) {
    return view('dashboard.content.product-index', [
        'site' => $site,
        'products' => $site->productListings()->latest()->get(),
    ]);
})->name('products.index');
Route::get('/sites/{site}/products/create', fn (Site $site) => view('dashboard.content.product-create', ['site' => $site]))->name('products.create');
Route::post('/sites/{site}/products', function (Request $request, Site $site) {
    $d = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string', 'price' => 'required|numeric|min:0', 'currency' => 'nullable|string|size:3', 'status' => 'nullable|string|max:32']);
    $site->productListings()->create(array_merge($d, ['currency' => $d['currency'] ?? 'EUR', 'status' => $d['status'] ?? 'draft']));

    return redirect("/dashboard/sites/{$site->id}/products")->with('success', 'Product created.');
})->name('products.store');
Route::get('/sites/{site}/products/{product}/edit', function (Site $site, ProductListing $product) {
    abort_unless($product->site_id === $site->id, 403);

    return view('dashboard.content.product-edit', ['site' => $site, 'product' => $product]);
})->withoutScopedBindings()->name('products.edit');
Route::put('/sites/{site}/products/{product}', function (Request $request, Site $site, ProductListing $product) {
    abort_unless($product->site_id === $site->id, 403);
    $d = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string', 'price' => 'required|numeric|min:0', 'currency' => 'nullable|string|size:3', 'status' => 'nullable|string|max:32']);
    $product->update($d);

    return redirect("/dashboard/sites/{$site->id}/products")->with('success', 'Product updated.');
})->withoutScopedBindings()->name('products.update');
Route::delete('/sites/{site}/products/{product}', function (Site $site, ProductListing $product) {
    abort_unless($product->site_id === $site->id, 403);
    $product->delete();

    return back()->with('success', 'Product deleted.');
})->withoutScopedBindings()->name('products.destroy');
// Templates
Route::get('/sites/{site}/templates', fn (Site $site) => view('dashboard.content.templates', ['site' => $site]))->name('templates.index');
Route::post('/sites/{site}/templates', function (Request $request, Site $site) {
    $d = $request->validate([
        'name' => 'required|string|max:255',
        'type' => 'nullable|string|max:64',
        'html_template' => 'nullable|string',
        'fields_schema' => 'nullable|array',
    ]);
    $site->contentTemplates()->create($d);

    return back()->with('success', 'Template created.');
})->name('templates.store');
Route::put('/sites/{site}/templates/{template}', function (Request $request, Site $site, ContentTemplate $template) {
    abort_unless($template->site_id === $site->id, 403);
    $d = $request->validate([
        'name' => 'required|string|max:255',
        'type' => 'nullable|string|max:64',
        'html_template' => 'nullable|string',
    ]);
    $template->update($d);

    return back()->with('success', 'Template saved.');
})->withoutScopedBindings()->name('templates.update');
Route::delete('/sites/{site}/templates/{template}', function (Site $site, ContentTemplate $template) {
    abort_unless($template->site_id === $site->id, 403);
    $template->delete();

    return back()->with('success', 'Template deleted.');
})->withoutScopedBindings()->name('templates.destroy');
