wpes-lib
========

WordPress-Elasticsearch Lib
---------------------------
A library for building WordPress Elasticsearch plugins

Design Goals:

1. Encourage a common ES WordPress schema so basic queries will work the same way for everyone.
2. Leave room for expanding schemas for plugins and future development.
3. Independent of ES Clients (ie Elastica, Sherlock, elasticsearch should all work)
4. Multi-lingual support from the start.

Glossary
 - Index Builder: creates the settings and configuration data for creating an index
 - Document Builder: creates the data structures needed for indexing/updating/getting a ES document
 - Analyzer Builder: creates the configuration data for creating analyzers for different languages.
 - Document Iterator: iterates over a set of documents (eg posts, comments, blogs) for easy bulk indexing (and deleting).

Also included:
- The index builder for indexing all posts into a VIP index (this is probably the best

Requirements:

- WordPress 3.5+
- Elasticsearch 2.x (should also works with 1.x, probably not with 5.x)
- To achieve multi-lingual support, this library requires the use of the following ES plugins
  https://github.com/elasticsearch/elasticsearch-analysis-icu (required by all analyzers)
  https://github.com/elasticsearch/elasticsearch-analysis-kuromoji
  https://github.com/elasticsearch/elasticsearch-analysis-smartcn
- When indexing attachment files, relies on the https://github.com/elastic/elasticsearch-mapper-attachments ES plugin. This is deprecated in 5.x in favor of an ingest api though.

Example Code (/examples/):

WordPress.com uses a lot of custom tables and extra complexity for index management and handles indexing using a custom asynchronous jobs system. Unfortunately none of this works well in a standard WordPress install. However, this directory contains some of our example code of how we are using wpes-lib to perform indexing.
