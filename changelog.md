### Release notes  for BTEC grading method for Moodle by Marcus Green

### Version 1.22 Aug 2018
Added privacy classes to help GDPR compliance. Confirmation it works with Moodle 3.5
Added btec-grade div/class so the display of final grade can be styld/hidden.
Codechecker compliance updates. 

### Version 1.21. April 2018
This is a maintenance release to confirm that it works with all versions of Moodle between
3.1 and 3.4. I have also tried it with an alpha of Moodle 3.5 without error.

#### Changed
I have fixed the string handling that should allow it to be translated using AMOS. It now passes
more code compliance/phpdoc tests and behat tests work. Thanks to Jean-Michel Vedrine for inspiration
to get my .travis.yml file working so that behat test work automatically when I do a git submit.


### Version 1.2. September 2016
I call this the Sue Moss release, in honour of the help she gave me with some of the ideas.
Updates to take account of the Moodle 3.1 grading interface, while
still working with earlier versions.
* Moved yes/no radio buttons to the left hand side of grading page
* Added radio buttons at the top to toggle all to yes or no
* Added behat tests (benefits to developers mainly)

### Version 1.1. March 2016
I call this the  Gloucestershire college release in honour of the help and feedback given on my visit there.
* Reduced the size of the description field as it was occupying a disproportional amount of space
* Added a check to ensure that the assignment has the BTEC scale set
* Changed CSS so the Add Criterion button lined up properly
* Changed Marking Options so it is hidden by default as it is not frequently used
* tweaked code (a constructor) to ensure it would work with PHP7


### beta1 Oct 2014
* Changed drop down with criteria level for a text box. This was because previously it was
* limited to 6 levels, i.e. P1 to P6, M1 to M6 etc and some places have more levels than that
* Added validation on save to check for duplicate level criteria. Changed CSS to reveal the X
* that indicates you can delete a level during editing. Changed CSS for layout at marking
* time as previously the labels on the Yes No radio buttons could be mistaken for each other.
