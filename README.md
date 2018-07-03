# WPML automatic language with GeoIP

Automatically changes the load of language in WPML based on the GeoIP service

## Features

- Compatible with WP Engine's GeoIP service
- Uses MaxMind GeoIP service
- Country code mapping
- Can set a custom preferred default language instead of WPML's

## Requirements

- PHP >=5.4
- WordPress 4.5.2
- WPML Multilingual CMS 3.3.8

## Altering the behaviour

You can create a `mu-plugin` which adds a filter to `wpml_automatic_language_with_geoip_preferred_default_language` hook to change the default language only for the frontend.

Also if the predefined ALPHA2 country code to language code map doesn't meet your requirements:
You can create a `mu-plugin` which adds a filter to `wpml_automatic_language_with_geoip_country_code_map` hook to change it's values.

## Licence

This product includes GeoLite2 data created by MaxMind, available from [http://www.maxmind.com](http://www.maxmind.com)