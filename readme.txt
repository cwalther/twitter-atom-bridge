Twitter to Atom Bridge
by Christian Walther <cwalther@gmx.ch>

In June 2013, Twitter disabled RSS/Atom feeds, as part of the retirement of API v1, citing that “XML, Atom, and RSS are infrequently used today, and we’ve chosen to throw our support behind the JSON format shared across the platform.” My feed reader doesn’t read JSON, so to restore my ability to follow people’s Twitter updates, I wrote this bridge script that uses the new Twitter API to read a user timeline and builds an Atom feed from the result.

How to use:

1. Go to https://dev.twitter.com/apps/ and create a new application.

2. Obtain its bearer token as described in https://dev.twitter.com/docs/auth/application-only-auth .

3. Insert the token into the script where it says so near the top.

4. Call it like http://example.com/twitter.php?screen_name=twitterapi&count=20 .
