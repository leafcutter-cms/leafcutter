window.onload=()=>{var lsc=(a,as)=>{if(a.length==0){return}
var x=new XMLHttpRequest();x.open('GET',a.shift());x.onload=()=>{if(x.status===200){var el=document.createElement('script');el.innerHTML=x.responseText;document.head.appendChild(el)}else{console.error(x.status)}
if(!as){lsc(a,as)}};x.send();if(as){lsc(a,as)}};var o=["http:\/\/localhost\/leafcutter\/web\/assets\/f\/9c\/6a\/77bf53d7d9b66c408147ca7e8f8\/jquery.js","http:\/\/localhost\/leafcutter\/web\/assets\/7\/d5\/ff\/51d594d16768cc49f5ed25f4d62\/jquery-ui.js","http:\/\/localhost\/leafcutter\/web\/assets\/3\/3f\/9e\/26b591c7ff5bf705c2c9152bc33\/_page.js"];var a=[];lsc(o,!1);lsc(a,!0)}