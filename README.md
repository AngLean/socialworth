# SocialWorth

SocialWorth determines the popularity of a given URL across social networks and search engines.

The following networks are currently supported:

* Facebook likes, comments, click throughs and shares (combined by default; seperately with minor modifications)
* Twitter mentions of the url (via tweets, retweets, shares, or anything else.)  
* Reddit (combines the number of submitted stories and upvotes)
* Hacker News (combines the number of submitted stories, points and comments)
* Google +1s
* StumbleUpon views
* LinkedIn shares
* Pinterest shares (note: the pinterest api is not officially open nor is it documented; it's also very unreliable in it's responses)
* Mozscape (for backlinks; requires a free account with their service)

## Configuration

If you wish to use the Mozscape API, you'll need to modify the script to include your account details. __The rest of the networks use public APIs and require no configuration.__

## Usage
When initializing the SocialWorth object, you may optionally provide an array of networks to query (otherwise it defaults to all). URL is the only required parameter. 


```
$networks = array('facebook', 'googleplus', 'pinterest', 'twitter');
$url      = 'http://www.reddit.com/r/IAmA/comments/1rm0id/i_just_drove_3121_miles_solo_from_mexico_to/';

$social = new SocialWorth($networks);
$worth  = $social->value($url);
```

The `value()` method returns an array of values for each network and an aggregate total:

```
Array
(
    [facebook]    => 314
    [googleplus]  => 9
    [hackernews]  => 0
    [linkedin]    => 6
    [mozscape]    => 0
    [pinterest]   => 0
    [reddit]      => 32
    [stumbleupon] => 76
    [twitter]     => 432
    [total]       => 869
)

```
