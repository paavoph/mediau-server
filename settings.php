<?php

// Whether to show album artwork
define("SHOW_ARTWORK", false);

// When set true, cached URLs are preferred over requesting the stream URL from MediaU service.
// When false, URLs are always retrieved from MediaU, and the cache is updated.
define("PREFER_CACHED_STREAM_URL", true);

// The URL prepended to artwork URLs provided by iTunes search API
// NB! Must match nginx configuration
define("ARTWORK_URL_PREFIX", "http://itunes.apple.com/artwork?url=");

// Path to the ICC profile applied to artwork images downloaded from iTunes
define("ITUNES_ICC", "assets/itunes.icc");

// Path to the file containing favorites shown under MediaU
define("FAVORITES_FILE", "conf/favorites.json");

// Configuration file containing stream URLs that have priority over all other sources
define("STREAM_CONFIG_FILE", "conf/streams.json");

// A file containing stream URLs for listened stations, used when unable to retrieve current stream URL
define("STREAM_CACHE_FILE", "cache/streams.json");

// Folder where channel logos will be cached
define("LOGO_CACHE_DIR", "cache/logo");
