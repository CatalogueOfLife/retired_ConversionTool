ABOUT TAXONMATCHER PHP
======================
TaxonMatcher PHP is a PHP adaptation of the original TaxonMatcher,
written in PERL by Richard White, Cardiff University.

The taxon matcher computes LSIDs for taxa. If a matching taxon in
the previous edition of the Catalogue of Life (CoL) is found, it
copies the LSID from that taxon. Otherwise the taxon gets assigned
a new LSID.

The logic and procedure for computing LSIDs have been copied from
the PERL version to the PHP version as-is. Some utility functions
in the PERL version have been left out. Moreover, the PHP version
is set up as a library rather than a stand-alone command line
script. However, in the demo directory you will find a script that
you can run as a stand-alone command line script:
taxonmatcher-cli.php.


GETTING STARTED
===============
The taxonmatcher-cli.php script is also a good place to start when
you want to understand the TaxonMatcher PHP code, because it shows
you how to instantiate, configure and run the central class in the
library: TaxonMatcher (defined in TaxonMatcher.php). Then go on to the
TaxonMatcher class itself and see how it employs the other classes
and interfaces in the library.


LICENSING
=========
See LICENCE.txt
