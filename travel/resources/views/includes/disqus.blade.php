<div class="container top-buffer2" id="disqus">
    <div class="row">
        <div id="disqus_thread" class="col-md-8 col-md-offset-2 col-xs-12"></div>
        <script>
            /**
             * RECOMMENDED CONFIGURATION VARIABLES: EDIT AND UNCOMMENT THE SECTION BELOW TO INSERT DYNAMIC VALUES FROM YOUR PLATFORM OR CMS.
             * LEARN WHY DEFINING THESE VARIABLES IS IMPORTANT: https://disqus.com/admin/universalcode/#configuration-variables
             */

             var disqus_config = function () {
             this.page.url = '{{ route('itinerary.show', $itinerary) }}}'; // Replace PAGE_URL with your page's canonical URL variable
             this.page.identifier = '{{ $itinerary->getRouteKey() }}}'; // Replace PAGE_IDENTIFIER with your page's unique identifier variable
             };

            (function() { // DON'T EDIT BELOW THIS LINE
                var d = document, s = d.createElement('script');

                s.src = '//tripplanshop.disqus.com/embed.js';

                s.setAttribute('data-timestamp', +new Date());
                (d.head || d.body).appendChild(s);
            })();
        </script>
        <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript" rel="nofollow">comments powered by Disqus.</a></noscript>



    </div>
</div>