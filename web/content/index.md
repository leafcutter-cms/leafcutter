Welcome to a website!

This page is being generated through a whole giant chain of medium-complicated stuff! It's not a CMS you've heard of, either. It's one I've been working on myself for quite some time.

Whether that complexity is good or not is up for debate. I, of all people, should be bothered by it. Few things are as important to me as the democratization of the web. That's why I build my own website, and encourage everyone with the inclination to do the same. Together we can break the silos and build a free-er, more durable, more interesting, and just plain weirder and more wonderful web.

I've even made a few interesting tools for doing that, if you're interested and happen to be on my particular [weirdo-90s-nostalgia brainwave](/hackery/programming/leafcutter/).

<ul>
{% for p in page.children.sortBy('date.modified',true) %}
<li>{{p|link}}</li>
{% endfor %}
</ul>

<!--@meta 
name: Home
 -->
 