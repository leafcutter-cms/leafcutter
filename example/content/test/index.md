# Test page

This is a test page

<ul>
{% for p in page.children %}
<li>{{p|link}}</li>
{% endfor %}
</ul>
