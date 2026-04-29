// PedalPal Bike Rentals — File Watcher
// Node 14. CommonJS. Callbacks. var declarations.
// A love letter to the era when "just nest another function" was considered fine.
// It was not fine. It worked. Those are different things.

// CommonJS require() — the original module system. Still works in Node 14.
// ESM (import/export) has been the recommended approach since Node 12.
// When Node finally drops CJS support you will know because everything will break
// and the error message will be very clear about what you should have done differently.
var fs   = require('fs');
var path = require('path');
var url  = require('url'); // url.parse() inside here is deprecated since Node 11. New URL() is the future. We live in the past.

// var. Not const. Not let. var.
// var is function-scoped and hoisted, which has caused more bugs than it has prevented.
// const and let arrived in Node 6. We are on Node 14. Still using var.
// Because that's the vibe. Because the assignment said so. Because old habits are comfortable.
var SAMPLE_DATA_DIR = path.join(__dirname, 'SampleData');
var LOG_PREFIX = '[PedalPal Watcher]';

// url.parse() — deprecated since Node 11. The docs say use `new URL()` instead.
// The docs have been saying this for years. The function still ships. Still runs.
// It will wave at you from the deprecation list every time you read the changelog.
var parsedWatcherUrl = url.parse('file://' + SAMPLE_DATA_DIR);
console.log(LOG_PREFIX + ' Watching directory: ' + parsedWatcherUrl.path);

// new Buffer() — deprecated since Node 6 (DEP0005) in favor of Buffer.from() and friends.
// Buffer.from('your string') is right there. Has been right there since 2016.
// The DEP0005 warning will appear in your console when this runs.
// Consider it the Node runtime clearing its throat disapprovingly.
// It still works. In Node 14, deprecated doesn't mean removed. It means "we are tired of this."
var startupMessage = new Buffer('PedalPal watcher started. Watching for changes in SampleData/.');
console.log(LOG_PREFIX + ' ' + startupMessage.toString());

/**
 * Given a data file path, returns the path to its .cache sidecar.
 * e.g. beach_cruisers.xml → beach_cruisers.xml.cache
 * The simplest function in this file. No deprecations. A palate cleanser.
 */
function getCachePath(filePath) {
    return filePath + '.cache';
}

/**
 * Delete the .cache sidecar for a changed data file so PHP will reload from source.
 *
 * Uses fs.exists() — deprecated since Node 4. The official replacement is fs.access()
 * or fs.stat(), because "check then act" is inherently racy and the Node team would
 * prefer you not do it. The deprecation notice in the docs is older than some interns.
 * fs.exists() still works in Node 14. It checks. It callbacks. It carries on.
 *
 * Then uses callback-style fs.unlink() instead of fs.promises.unlink().
 * fs.promises has been stable since Node 10. util.promisify() since Node 8.
 * async/await since Node 7.6. We are using none of those things.
 * Callbacks in callbacks: the original async story. Works from Node 0.1 to present day.
 */
function deleteCacheFile(cachePath) {
    // fs.exists() — deprecated in Node 4. Every version since has suggested fs.access() instead.
    // Here we check-then-act with full awareness of the TOCTOU race condition.
    // The bike rental demo does not have concurrent cache deletion. We accept the risk.
    fs.exists(cachePath, function(exists) {
        if (exists) {
            // Callback-style fs.unlink(). Not a Promise. Not awaitable.
            // A function that calls another function when it is done.
            // The classic. The original. The thing async/await was invented to replace.
            fs.unlink(cachePath, function(err) {
                if (err) {
                    console.error(LOG_PREFIX + ' Failed to delete cache: ' + cachePath + ' — ' + err.message);
                } else {
                    console.log(LOG_PREFIX + ' Cache invalidated: ' + path.basename(cachePath));
                }
            });
        } else {
            console.log(LOG_PREFIX + ' No cache to invalidate for: ' + path.basename(cachePath));
        }
    });
}

// fs.watch() — the classic, not-quite-reliable, platform-inconsistent file watcher.
// Works in Node 14. Has always worked. The callback receives event type and filename.
// On some platforms, filename is null. On some, the event fires twice for one change.
// On Linux you usually get it right. On macOS you get it right until you don't.
// This is documented behavior. "Works on my machine" is a valid deployment strategy
// for a demo app started in 2008, and here we are.
fs.watch(SAMPLE_DATA_DIR, { recursive: false }, function(eventType, filename) {
    if (!filename) {
        // Some platforms omit the filename. Nothing to do. Log it. Move on.
        // Being graceful about platform limitations is a sign of wisdom,
        // not an endorsement of the API that requires it.
        console.log(LOG_PREFIX + ' Change detected but filename was null. Platform issue. Shrug.');
        return;
    }

    // Skip .cache file changes — those are written by PHP, not by humans.
    // Watching the cache would be watching ourselves. Philosophical and also infinite.
    if (filename.endsWith('.cache')) {
        return;
    }

    console.log(LOG_PREFIX + ' Change detected: ' + filename + ' (event: ' + eventType + ')');

    var changedFilePath = path.join(SAMPLE_DATA_DIR, filename);
    var cachePath = getCachePath(changedFilePath);

    deleteCacheFile(cachePath);
});

console.log(LOG_PREFIX + ' Watching ' + SAMPLE_DATA_DIR + ' for changes...');
console.log(LOG_PREFIX + ' When a data file changes, its .cache sidecar will be deleted so PHP reloads fresh.');
console.log(LOG_PREFIX + ' Press Ctrl+C to stop. Or close the terminal. Or just leave it running.');
console.log(LOG_PREFIX + ' It uses almost no memory. The bar was low. We cleared it.');
