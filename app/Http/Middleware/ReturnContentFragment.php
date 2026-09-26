<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReturnContentFragment
{
    /**
     * Handle an incoming request.
     * If AJAX, return only the content section of Blade-rendered views.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only act on AJAX/XHR requests that explicitly request a partial fragment
        if (! $request->ajax() || ! $request->headers->has('X-Partial')) {
            return $response;
        }

        // If controller returned a Laravel View instance, render its sections
        if (is_object($response) && method_exists($response, 'renderSections')) {
            try {
                $sections = $response->renderSections();
                $payload = $sections['content'] ?? $response->render();
                // If the view rendered a scripts section (e.g. @push('scripts')), include it
                if (! empty($sections['scripts'])) {
                    $payload .= $sections['scripts'];
                }

                return response($payload);
            } catch (\Throwable $e) {
                // fall through to default handling
            }
        }

        // If response is a Symfony Response with HTML, attempt to extract the mainBodyContent element
        if ($response instanceof Response) {
            $content = $response->getContent();
            if (is_string($content)) {
                // Try a simple DOM parse to extract #mainBodyContent
                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                if (@$doc->loadHTML($content)) {
                    $node = $doc->getElementById('mainBodyContent');
                    if ($node) {
                        // Keep the #mainBodyContent wrapper itself (not just its
                        // children) so the client's doc.getElementById(contentId)
                        // lookup in ajaxNavigate() can find it.
                        $payload = $doc->saveHTML($node);

                        // Per-page @section('scripts') and @push('scripts') blocks both
                        // render outside #mainBodyContent (see layouts/_partials/_scripts.blade.php
                        // and the #page-stack-scripts wrapper in layouts/app.blade.php), so
                        // without this they're silently dropped from every AJAX-navigated
                        // page and never execute.
                        foreach (['page-scripts', 'page-stack-scripts'] as $scriptsContainerId) {
                            $scriptsNode = $doc->getElementById($scriptsContainerId);
                            if ($scriptsNode) {
                                $payload .= $doc->saveHTML($scriptsNode);
                            }
                        }

                        // Per-page @section('styles')/@push('styles') blocks render in
                        // <head> (see #page-styles in layouts/_partials/_head.blade.php),
                        // which the AJAX fragment swap never touches, so without this
                        // page-specific CSS is silently dropped on every AJAX navigation.
                        $stylesNode = $doc->getElementById('page-styles');
                        if ($stylesNode) {
                            $payload .= $doc->saveHTML($stylesNode);
                        }

                        return response($payload, $response->getStatusCode(), $response->headers->all());
                    }
                }
            }
        }

        return $response;
    }
}
