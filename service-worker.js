/**
 * Pup Coupons Service Worker
 * Enhanced with better error handling, logging, and performance optimization
 */

const CACHE_NAME = 'love-coupons-plugin-v1.1';
const STATIC_CACHE_NAME = 'love-coupons-static-v1.1';
const DYNAMIC_CACHE_NAME = 'love-coupons-dynamic-v1.1';
const PLUGIN_SCOPE = '/wp-content/plugins/love-coupons/';

// Static assets to cache immediately
const STATIC_ASSETS = [
    PLUGIN_SCOPE,
    PLUGIN_SCOPE + 'assets/js/love-coupons.js',
    PLUGIN_SCOPE + 'assets/css/love-coupons.css',
    PLUGIN_SCOPE + 'manifest.json',
    PLUGIN_SCOPE + 'assets/images/icon192.png',
    PLUGIN_SCOPE + 'assets/images/icon512.png'
];

// Cache configuration
const CACHE_CONFIG = {
    maxAge: 24 * 60 * 60 * 1000, // 24 hours
    maxEntries: 50,
    networkTimeoutMs: 3000
};

/**
 * Install Event - Cache static assets
 */
self.addEventListener('install', event => {
    console.log('[Pup Coupons SW] Installing service worker...');
    
    event.waitUntil(
        Promise.all([
            // Cache static assets
            caches.open(STATIC_CACHE_NAME)
                .then(cache => {
                    console.log('[Pup Coupons SW] Caching static assets...');
                    return cache.addAll(STATIC_ASSETS);
                })
                .catch(error => {
                    console.error('[Pup Coupons SW] Failed to cache static assets:', error);
                    // Don't fail installation if caching fails
                    return Promise.resolve();
                }),
            
            // Skip waiting to activate immediately
            self.skipWaiting()
        ])
    );
});

/**
 * Activate Event - Clean up old caches
 */
self.addEventListener('activate', event => {
    console.log('[Pup Coupons SW] Activating service worker...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            cleanupOldCaches(),
            
            // Claim all clients
            self.clients.claim()
        ])
    );
});

/**
 * Fetch Event - Handle network requests
 */
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests from same origin
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // Check if request is within our scope
    if (!isPluginRequest(url)) {
        return;
    }

    console.log('[Pup Coupons SW] Handling request:', url.pathname);

    // Route requests based on type
    if (isStaticAsset(url.pathname)) {
        event.respondWith(handleStaticAsset(request));
    } else if (isImageRequest(url.pathname)) {
        event.respondWith(handleImageRequest(request));
    } else if (isApiRequest(url.pathname)) {
        event.respondWith(handleApiRequest(request));
    } else {
        event.respondWith(handleOtherRequest(request));
    }
});

/**
 * Message Event - Handle messages from main thread
 */
self.addEventListener('message', event => {
    console.log('[Pup Coupons SW] Received message:', event.data);
    
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'SKIP_WAITING':
                self.skipWaiting();
                break;
            case 'CACHE_UPDATE':
                updateCache(event.data.url);
                break;
            case 'CLEAR_CACHE':
                clearCache();
                break;
            default:
                console.warn('[Pup Coupons SW] Unknown message type:', event.data.type);
        }
    }
});

/**
 * Check if request is related to our plugin
 */
function isPluginRequest(url) {
    return url.pathname.startsWith(PLUGIN_SCOPE) ||
           url.pathname.includes('love-coupons') ||
           url.pathname.includes('love_coupon') ||
           url.searchParams.has('pup-coupon');
}

/**
 * Check if request is for a static asset
 */
function isStaticAsset(pathname) {
    return STATIC_ASSETS.some(asset => pathname.endsWith(asset.split('/').pop())) ||
           pathname.match(/\.(js|css|json|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/);
}

/**
 * Check if request is for an image
 */
function isImageRequest(pathname) {
    return pathname.startsWith('/wp-content/uploads/') &&
           pathname.match(/\.(png|jpg|jpeg|gif|svg|webp)$/);
}

/**
 * Check if request is for API/AJAX
 */
function isApiRequest(pathname) {
    return pathname.includes('admin-ajax.php') ||
           pathname.includes('wp-json/') ||
           pathname.includes('rest');
}

/**
 * Handle static asset requests (cache-first)
 */
async function handleStaticAsset(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request, {
            cacheName: STATIC_CACHE_NAME
        });
        
        if (cachedResponse) {
            console.log('[Pup Coupons SW] Serving from cache:', request.url);
            
            // Update cache in background for next time
            updateCacheInBackground(request);
            
            return cachedResponse;
        }

        // Fallback to network
        console.log('[Pup Coupons SW] Fetching from network:', request.url);
        const networkResponse = await fetchWithTimeout(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('[Pup Coupons SW] Error handling static asset:', error);
        return createErrorResponse('Static asset not available');
    }
}

/**
 * Handle image requests (cache-first with expiration)
 */
async function handleImageRequest(request) {
    try {
        const cachedResponse = await caches.match(request, {
            cacheName: DYNAMIC_CACHE_NAME
        });
        
        if (cachedResponse && !isCacheExpired(cachedResponse)) {
            return cachedResponse;
        }

        const networkResponse = await fetchWithTimeout(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            const responseWithTimestamp = addTimestampHeader(networkResponse.clone());
            cache.put(request, responseWithTimestamp);
            
            // Clean up old entries
            await cleanupCache(DYNAMIC_CACHE_NAME, CACHE_CONFIG.maxEntries);
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('[Pup Coupons SW] Error handling image:', error);
        
        // Return cached version even if expired as fallback
        const cachedResponse = await caches.match(request, {
            cacheName: DYNAMIC_CACHE_NAME
        });
        
        return cachedResponse || createErrorResponse('Image not available');
    }
}

/**
 * Handle API requests (network-first)
 */
async function handleApiRequest(request) {
    try {
        const networkResponse = await fetchWithTimeout(request, 5000);
        return networkResponse;
        
    } catch (error) {
        console.error('[Pup Coupons SW] API request failed:', error);
        
        // For specific endpoints, try cache fallback
        if (request.url.includes('love_coupons')) {
            const cachedResponse = await caches.match(request);
            if (cachedResponse) {
                return cachedResponse;
            }
        }
        
        return createErrorResponse('API not available', 503);
    }
}

/**
 * Handle other requests (network-first with cache fallback)
 */
async function handleOtherRequest(request) {
    try {
        const networkResponse = await fetchWithTimeout(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('[Pup Coupons SW] Request failed:', error);
        
        const cachedResponse = await caches.match(request);
        return cachedResponse || createErrorResponse('Content not available');
    }
}

/**
 * Fetch with timeout
 */
function fetchWithTimeout(request, timeout = CACHE_CONFIG.networkTimeoutMs) {
    return Promise.race([
        fetch(request, { credentials: 'include' }),
        new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Network timeout')), timeout)
        )
    ]);
}

/**
 * Update cache in background
 */
function updateCacheInBackground(request) {
    // Don't await this - run in background
    fetchWithTimeout(request)
        .then(response => {
            if (response.ok) {
                return caches.open(STATIC_CACHE_NAME)
                    .then(cache => cache.put(request, response));
            }
        })
        .catch(error => {
            console.warn('[Pup Coupons SW] Background cache update failed:', error);
        });
}

/**
 * Add timestamp header to response
 */
function addTimestampHeader(response) {
    const headers = new Headers(response.headers);
    headers.set('sw-cached-at', Date.now().toString());
    
    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers
    });
}

/**
 * Check if cached response is expired
 */
function isCacheExpired(response) {
    const cachedAt = response.headers.get('sw-cached-at');
    if (!cachedAt) return false;
    
    const age = Date.now() - parseInt(cachedAt);
    return age > CACHE_CONFIG.maxAge;
}

/**
 * Clean up old caches
 */
async function cleanupOldCaches() {
    try {
        const cacheNames = await caches.keys();
        const oldCacheNames = cacheNames.filter(name => 
            name.startsWith('love-coupons') && 
            name !== STATIC_CACHE_NAME && 
            name !== DYNAMIC_CACHE_NAME
        );
        
        console.log('[Pup Coupons SW] Cleaning up old caches:', oldCacheNames);
        
        await Promise.all(
            oldCacheNames.map(name => caches.delete(name))
        );
        
    } catch (error) {
        console.error('[Pup Coupons SW] Error cleaning up caches:', error);
    }
}

/**
 * Clean up cache entries by size
 */
async function cleanupCache(cacheName, maxEntries) {
    try {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        
        if (keys.length > maxEntries) {
            const entriesToDelete = keys.slice(0, keys.length - maxEntries);
            await Promise.all(
                entriesToDelete.map(key => cache.delete(key))
            );
            
            console.log(`[Pup Coupons SW] Cleaned up ${entriesToDelete.length} entries from ${cacheName}`);
        }
        
    } catch (error) {
        console.error('[Pup Coupons SW] Error cleaning up cache entries:', error);
    }
}

/**
 * Update specific cache entry
 */
async function updateCache(url) {
    try {
        const response = await fetch(url);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE_NAME);
            await cache.put(url, response);
            console.log('[Pup Coupons SW] Cache updated for:', url);
        }
    } catch (error) {
        console.error('[Pup Coupons SW] Error updating cache:', error);
    }
}

/**
 * Clear all plugin caches
 */
async function clearCache() {
    try {
        const cacheNames = [STATIC_CACHE_NAME, DYNAMIC_CACHE_NAME];
        await Promise.all(
            cacheNames.map(name => caches.delete(name))
        );
        console.log('[Pup Coupons SW] All caches cleared');
    } catch (error) {
        console.error('[Pup Coupons SW] Error clearing caches:', error);
    }
}

/**
 * Create error response
 */
function createErrorResponse(message, status = 404) {
    return new Response(
        JSON.stringify({ error: message, timestamp: new Date().toISOString() }),
        {
            status: status,
            statusText: message,
            headers: { 'Content-Type': 'application/json' }
        }
    );
}

/**
 * Log performance metrics
 */
function logPerformance(startTime, url, source) {
    const duration = performance.now() - startTime;
    console.log(`[Pup Coupons SW] ${url} served from ${source} in ${duration.toFixed(2)}ms`);
}

console.log('[Pup Coupons SW] Service worker loaded successfully');
