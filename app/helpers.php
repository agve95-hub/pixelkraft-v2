<?php

if (! function_exists('csp_nonce')) {
    /**
     * Return the Content-Security-Policy nonce for the current request.
     *
     * The nonce is generated once per request by SetSecurityHeaders middleware
     * and stored in the service container under the 'csp-nonce' key.
     *
     * Use this in Blade templates on any <script> or <style> tag that should
     * be permitted by the CSP header:
     *
     *   <script @cspNonce>…</script>
     *   <script nonce="{{ csp_nonce() }}">…</script>
     */
    function csp_nonce(): string
    {
        return (string) app('csp-nonce');
    }
}
