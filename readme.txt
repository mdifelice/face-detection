=== Face Detection ===
Contributors: mdifelice
Tags: face detection, thumbnails
Tested up to: 4.9.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin allows to generate cropped thumbnails without cutting heads or
faces. It detects whether uploaded images have faces in it thus when generating
cropped thumbnails based on such image, they will be centered in the largest
face found.

=== Frequently Asked Questions ===

= What does this plugin do? =

This plugin modifies those cropped thumbnails generated from uploaded images
in such way whether there is a face inside that image, it will be in the
center of the generated thumbnail. That way, it avoids cut heads or faces when
displaying thumbnails of your images.

= How does it detect faces? =

This plugin uses a custom WordPress/PHP port of a [Java
implementation](https://code.google.com/archive/p/jviolajones/) of the
[Viola/Jones](https://en.wikipedia.org/wiki/Viola%E2%80%93Jones_object_detection_framework)
object detection algorithm.  Very technical stuff. Basically, you need to know
that it is not 100% effective. But the idea is to improve its effectiveness in
subsequent versions.

= How I make it work? =

By default, the plugin is disabled for all the image uploads. It is done that
way because the process to detect faces is very heavy and maybe you do not
want to wait that each image you upload to the server be processed for this
plugin.

You can enable *Face Detection* for all uploads by going to *Settings -> Media*.
But also, you can regenerate face-detected thumbnails for each image by going
to *Media* and editing a particular image. 

== Screenshots ===

1. Generated thumbnails for an image before installing the plugin.
2. Generated thumbnails for an image analyzed by the Face Detection plugin.
