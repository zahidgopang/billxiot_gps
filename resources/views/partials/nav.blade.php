<nav class="navbar navbar-expand-lg navbar-light bg-white">
  <div class="container-fluid">
    <a class="navbar-brand brand-logo-slot d-flex align-items-center" href="{{ url('/') }}" aria-label="{{ config('branding.name') }}">
        @include('partials.brand-logo')
    </a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        @auth
          <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>
          <li class="nav-item">
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link">Logout</button></form>
          </li>
        @else
          <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li>
        @endauth
      </ul>
    </div>
  </div>
</nav>
