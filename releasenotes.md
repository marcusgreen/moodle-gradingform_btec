
###Release notes  for BTEC grading method for Moodle by Marcus Green

####Version 1.2. September 2016
I call this the Sue Moss release, in honour of the help she gave me with some of the ideas.
Updates to take account of the Moodle 3.1 grading interface, while
still working with earlier versions.
* Moved yes/no radio buttons to the left hand side of grading page
* Added radio buttons at the top to toggle all to yes or no
* Added behat tests (benefits to developers mainly)

####Version 1.1. March 2016
I call this the  Gloucestershire college release in honour of the help and feedback given on my visit there.
* Reduced the size of the description field as it was occupying a disproportional amount of space
* Added a check to ensure that the assignment has the BTEC scale set
* Changed CSS so the Add Criterion button lined up properly
* Changed Marking Options so it is hidden by default as it is not frequently used
* tweaked code (a constructor) to ensure it would work with PHP7


####beta1 Oct 2014
* Changed drop down with criteria level for a text box. This was because previously it was
* limited to 6 levels, i.e. P1 to P6, M1 to M6 etc and some places have more levels than that
* Added validation on save to check for duplicate level criteria. Changed CSS to reveal the X
* that indicates you can delete a level during editing. Changed CSS for layout at marking
* time as previously the labels on the Yes No radio buttons could be mistaken for each other.

