
<article>
            <div class="inner-wrap">
                <h3>{{ $place->place_title }}</h3>

                <div>
                    @if( $place->image_path == '')
                        <img class="photo" src="{{ $place->photo_ref_google }}" alt="{{$place->place_title}}">
                    @else
                        <img class="photo" src="{{ asset($place->image_path) }}" alt="{{$place->place_title}}">
                    @endif
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <ul class="list-unstyled summary-list">

                            @if($place->place_address != '')
                                <li>
                                    <i class="fa fa-map-marker fa-fw" aria-hidden="true"></i>
                                    <div class="name">{{ $place->place_address }}</div>
                                </li>
                            @endif

                            @if($place->website != '')
                                <li>
                                    <i class="fa fa-globe fa-fw" aria-hidden="true"></i>
                                    <div class="web-link"><a target="_blank" href="{{ $place->website }}">{{ $place->website }}</a></div>
                                </li>
                            @endif

                            @if($place->business_hours != '')
                                <li>
                                    <i class="fa fa-clock-o fa-fw" aria-hidden="true"></i>
                                    <ul class="list-unstyled business_hours">
                                        @foreach( explode(',', $place->business_hours) as $h)
                                            <li class="">
                                                {{ $h  }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        </ul>
                        <table class="table table-hover table-condensed bottom-buffer">
                            <tbody>
                            <tr>
                                <td>Best time to visit</td>
                                <td class="text-right">{{ $place->time_to_visit }}</td>
                            </tr>
                            <tr>
                                <td>Duration</td>
                                <td class="text-right">{{ $place->duration }}</td>
                            </tr>
                            <tr>
                                <td>Suggested transportation</td>
                                <td  class="text-right">{{ $place->public_transit }}</td>
                            </tr>

                            <tr>
                                <td>Category</td>
                                <td  class="text-right">
                                    <div class=" route-item-exp">
                                        @if( $place->experiences != '' )
                                        @foreach( array_intersect_key($experiences, array_flip($place->experiences)) as $exp)
                                            <i class="fa fa-hashtag fa-fw" aria-hidden="true"></i>{{$exp}}
                                        @endforeach
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            </tbody>

                        </table>
                    </div>

                </div>


                <div>
                    <p class="route-detail-title">About</p>
                    <div>{!! $place->place_intro !!}</div>


                    @if($place->to_do != '')
                        <p class="route-detail-title top-buffer">What to do</p>
                        <div>{!! $place->to_do !!}</div>
                    @endif

                    @if($place->tips != '')
                        <p class="route-detail-title top-buffer">Tips</p>
                        <div>{!! $place->tips !!}</div>
                    @endif

                    @if($place->transportation != '')
                        <p class="route-detail-title top-buffer">Transportation</p>
                        <div>{!! $place->transportation !!}</div>
                    @endif

                    @if($place->restaurants != '')
                        <p class="route-detail-title top-buffer">Food nearby</p>
                        <div>{!! $place->restaurants !!}</div>
                    @endif

                    @if($place->info_links != '')
                        <p class="route-detail-title top-buffer">Useful links</p>
                        <div>{!! $place->info_links !!}</div>
                    @endif
                </div>
            </div>
        </article>
