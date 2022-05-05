@extends('page_layouts.base')

@section('body')
  <body style="background: #FBFBFB;">

    <div class="container-fluid body vh-100" style="overflow-x: hidden;overflow-y: auto;">
      
      <div class="main_container">
        @yield('content')
      </div>
      
    </div>

    @include('page_layouts._scripts')
  </body>
@stop
