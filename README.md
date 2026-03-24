# Glycerine IIIF Viewer

An Omeka S module which displays IIIF manifests in item pages and site blocks using 
[Glycerine Viewer](https://glycerine.io/viewer/).

## Overview

The Glycerine IIIF Viewer module allows Omeka S sites to embed IIIF manifests using the lightweight Glycerine Viewer. It
supports both automatic rendering from item metadata and manual embedding via site blocks.

## Installation

- Download a ZIP package from one of the [releases](https://github.com/Systemik-Solutions/OmekaS-GlycerineViewer/releases) 
in this repository.
- Extract the ZIP into the modules directory of your Omeka S installation.
- Rename the extracted folder to `GlycerineIIIFViewer`.
- In the Omeka S admin panel, navigate to Modules and click Install next to “Glycerine IIIF Viewer”.

For detailed instructions, refer to the [Omeka S module installation guide](https://omeka.org/s/docs/user-manual/modules/).

## Configuration

Choose one properties (e.g., dcterms:source, foaf:homepage) that may contain IIIF manifest URLs in the module 
configuration page. When rendering item pages, any valid IIIF manifest URL will be displayed in Glycerine Viewer.

## Usage

### Automatic Item Rendering

If an item contains the configured property with a valid IIIF manifest URL, Glycerine Viewer will be embedded 
automatically on the item’s internal and public pages.

### Manual Embedding via Site Block

Use the “Glycerine Viewer” block to embed Glycerine Viewer in site pages.

## Credits

Inspired by the [Mirador Viewer module](https://omeka.org/s/modules/Mirador/) for Omeka S.
