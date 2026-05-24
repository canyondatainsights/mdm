<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WikiPage;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/** Read-only wiki browsing for the web app: list curated pages and read one (markdown body from disk). */
class WikiController extends Controller
{
    /** Wiki pages grouped-ready for the browser (ordered by section, then title). */
    public function index()
    {
        $pages = WikiPage::orderBy('section')->orderBy('title')->get()->map(fn (WikiPage $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'path' => $p->path,
            'section' => $p->section,
            'mdm_vendor' => $p->mdm_vendor,
            'data_platform' => $p->data_platform,
            'domain' => $p->domain,
            'scope' => $p->scope,
            'tags' => array_values(array_filter([$p->mdm_vendor, $p->data_platform, $p->domain, $p->financial_model])),
            'updated' => optional($p->page_updated_at)->toDateString(),
        ]);

        return ['count' => $pages->count(), 'pages' => $pages];
    }

    /** One wiki page with its markdown body (canonical copy is the on-disk file). */
    public function show(string $path)
    {
        $rel = ltrim($path, '/');
        $page = WikiPage::where('path', $rel)->firstOrFail();

        $abs = rtrim(config('mdm.kb_path'), '/').'/'.$page->path;
        $body = is_file($abs)
            ? trim(YamlFrontMatter::parse((string) file_get_contents($abs))->body())
            : '';

        return [
            'id' => $page->id,
            'path' => $page->path,
            'title' => $page->title,
            'section' => $page->section,
            'body' => $body,
            'mdm_vendor' => $page->mdm_vendor,
            'data_platform' => $page->data_platform,
            'domain' => $page->domain,
            'scope' => $page->scope,
            'product' => $page->product,
            'product_version' => $page->product_version,
            'financial_model' => $page->financial_model,
            'tags' => array_values(array_filter([$page->mdm_vendor, $page->data_platform, $page->domain, $page->financial_model])),
            'updated' => optional($page->page_updated_at)->toDateString(),
        ];
    }
}
