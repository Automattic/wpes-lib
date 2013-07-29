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


Requirements:

- WordPress 3.5+
- To achieve multi-lingual support, this library requires the use of the following ES plugins
  https://github.com/elasticsearch/elasticsearch-analysis-icu (required by all analyzers)
  https://github.com/elasticsearch/elasticsearch-analysis-kuromoji
  https://github.com/elasticsearch/elasticsearch-analysis-smartcn
