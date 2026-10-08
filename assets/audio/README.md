# BUBAPC - audio files

This folder holds the sound files the website plays: recordings, background
music, and anything an event or sermon page links to.

It is here, rather than next to the CSS, so that a person adding a file knows
where to put it without having to look. The website always links to a file
with `asset('audio/name.mp3')`, so a file dropped in this folder is reachable
straight away — no list to update.

Keep files small. A single track longer than about 2 MB makes the page slow
for people on a phone, which is how most visitors here will arrive.

Nothing in this folder is used yet. The folder is committed so that it still
exists on a fresh clone of the project.
