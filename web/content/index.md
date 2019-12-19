# Home page

{% for p in pages.children(page).sortBy('getDateModified',true) %}
 * {{p|raw}}
{% endfor %}

<!--@meta 
name: Home
 -->
