# character-assassin

A plugin for torture testing WordPress themes and plugins. DO NOT USE ON A PRODUCTION SITE.

## Warning

Character Assassin is a development and testing tool for WordPress code. It works by filtering and injecting data at various points in order to expose data that is unescaped at critical points of input and output.

In other words, **this plugin intentionally corrupts data**.

Do not use it on any site that is connected with production data, or even persistent test data. That includes using it in an environment that shares a cache, CDN, or filesystem with production or staging sites.

**It is only safe for local test sites that use disposable data**

For safety I would encourage you only to use via `wp-now` and similar test environments.

## Who is it for?

This tool is intended mainly for plugin and theme developers, testers, and reviewers.

## What does it do?

When the plugin is activated, Character Assassin will filter key functions in the WordPress API to deliberately introduce unexpected values with special characters. At the end of every page view it will examine the output and program state for values that have not been correctly escaped with `esc_html()` and friends.

## How do I use it?

## How does it work?