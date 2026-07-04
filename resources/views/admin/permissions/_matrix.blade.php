<ul class="nav nav-tabs perm-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
    @foreach($grouped as $module => $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link @if($loop->first) active @endif"
                    data-bs-toggle="tab"
                    data-bs-target="#perm-tab-{{ $module }}"
                    type="button">
                {{ $tab['label'] }}
                <span class="badge bg-secondary-subtle text-secondary ms-1 perm-tab-count" data-module="{{ $module }}">{{ $tab['count'] }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
    @foreach($grouped as $module => $tab)
        <div class="tab-pane fade @if($loop->first) show active @endif" id="perm-tab-{{ $module }}">
            <div class="accordion perm-accordion" id="accordion-{{ $module }}">
                @foreach($tab['categories'] as $category => $groups)
                    @php
                        $catId = 'cat-'.$module.'-'.preg_replace('/[^a-z0-9]+/i', '-', strtolower($category));
                        $catTotal = 0;
                        $catGranted = 0;
                        $isCheckbox = ($inputType ?? 'checkbox') === 'checkbox';
                        foreach ($groups as $group) {
                            foreach ($group['permissions'] as $perm) {
                                $catTotal++;
                                $k = $perm->key;
                                if ($isCheckbox) {
                                    if (isset($grantedKeys[$k])) {
                                        $catGranted++;
                                    }
                                } elseif (($effectiveKeys[$k] ?? false)) {
                                    $catGranted++;
                                }
                            }
                        }
                    @endphp
                    <div class="accordion-item perm-accordion__item" data-category="{{ $category }}">
                        <h2 class="accordion-header d-flex align-items-stretch">
                            <button class="accordion-button @if(! $loop->first) collapsed @endif flex-grow-1" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#{{ $catId }}">
                                <span class="d-flex flex-wrap align-items-center gap-2 w-100">
                                    <span><i class="fas fa-folder-open me-2 text-primary"></i>{{ $category }}</span>
                                    <span class="badge bg-light text-dark border perm-cat-count"
                                          data-cat-id="{{ $catId }}"
                                          data-total="{{ $catTotal }}">{{ $catGranted }}/{{ $catTotal }}</span>
                                </span>
                            </button>
                            @if($isCheckbox)
                                <div class="perm-cat-actions d-flex align-items-center px-2 border-start bg-light">
                                    <button type="button"
                                            class="btn btn-link btn-sm text-success perm-cat-select-all"
                                            data-cat-id="{{ $catId }}"
                                            title="{{ __('app.permissions.select_category_all') }}">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-link btn-sm text-danger perm-cat-clear-all"
                                            data-cat-id="{{ $catId }}"
                                            title="{{ __('app.permissions.clear_category_all') }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endif
                        </h2>
                        <div id="{{ $catId }}" class="accordion-collapse collapse @if($loop->first) show @endif">
                            <div class="accordion-body">
                                @foreach($groups as $groupKey => $group)
                                    @if($groupKey !== '_default' && count($groups) > 1)
                                        <h6 class="perm-group-label">{{ $group['label'] }}</h6>
                                    @endif
                                    <div class="row g-2 mb-3">
                                        @foreach($group['permissions'] as $permission)
                                            @php
                                                $key = $permission->key;
                                                $overrideState = $userOverrideStates[$key] ?? '';
                                                $roleGranted = $roleGrantedKeys[$key] ?? false;
                                                $effective = $effectiveKeys[$key] ?? false;
                                                $cardStateClass = $isCheckbox
                                                    ? (isset($grantedKeys[$key]) ? 'is-granted' : '')
                                                    : match ($overrideState) {
                                                        '1' => 'is-grant',
                                                        '0' => 'is-deny',
                                                        default => 'is-inherit',
                                                    };
                                                $radioId = 'perm-'.md5($key);
                                            @endphp
                                            <div class="col-lg-6 col-xl-4 perm-item"
                                                 data-search="{{ strtolower($permission->key.' '.$permission->display_name.' '.$permission->description) }}"
                                                 data-override="{{ $overrideState }}"
                                                 data-effective="{{ $effective ? '1' : '0' }}"
                                                 data-cat-id="{{ $catId }}">
                                                <div class="perm-card {{ $cardStateClass }} @if(! $isCheckbox && $effective) is-effective @endif">
                                                    @if($isCheckbox)
                                                        <label class="perm-card__label w-100 m-0">
                                                            <input type="checkbox"
                                                                   name="permissions[]"
                                                                   value="{{ $key }}"
                                                                   class="perm-card__check"
                                                                   @checked(isset($grantedKeys[$key]))>
                                                            <span class="perm-card__body">
                                                                @if($permission->icon)
                                                                    <i class="fas {{ $permission->icon }} perm-card__icon"></i>
                                                                @endif
                                                                <span class="perm-card__title">{{ $permission->display_name }}</span>
                                                                <span class="perm-card__key">{{ $key }}</span>
                                                                @if($permission->description)
                                                                    <span class="perm-card__desc">{{ $permission->description }}</span>
                                                                @endif
                                                            </span>
                                                        </label>
                                                    @else
                                                        <div class="perm-tristate btn-group btn-group-sm w-100" role="group" aria-label="{{ $permission->display_name }}">
                                                            <input type="radio"
                                                                   class="btn-check perm-state-radio"
                                                                   name="overrides[{{ $key }}]"
                                                                   id="{{ $radioId }}-inherit"
                                                                   value=""
                                                                   data-key="{{ $key }}"
                                                                   @checked($overrideState === '')>
                                                            <label class="btn btn-outline-secondary" for="{{ $radioId }}-inherit" title="{{ __('app.permissions.state_inherit_desc') }}">
                                                                <i class="fas fa-minus d-md-none"></i>
                                                                <span class="d-none d-md-inline">{{ __('app.permissions.state_inherit') }}</span>
                                                            </label>

                                                            <input type="radio"
                                                                   class="btn-check perm-state-radio"
                                                                   name="overrides[{{ $key }}]"
                                                                   id="{{ $radioId }}-grant"
                                                                   value="1"
                                                                   data-key="{{ $key }}"
                                                                   @checked($overrideState === '1')>
                                                            <label class="btn btn-outline-success" for="{{ $radioId }}-grant" title="{{ __('app.permissions.state_grant_desc') }}">
                                                                <i class="fas fa-check d-md-none"></i>
                                                                <span class="d-none d-md-inline">{{ __('app.permissions.state_grant') }}</span>
                                                            </label>

                                                            <input type="radio"
                                                                   class="btn-check perm-state-radio"
                                                                   name="overrides[{{ $key }}]"
                                                                   id="{{ $radioId }}-deny"
                                                                   value="0"
                                                                   data-key="{{ $key }}"
                                                                   @checked($overrideState === '0')>
                                                            <label class="btn btn-outline-danger" for="{{ $radioId }}-deny" title="{{ __('app.permissions.state_deny_desc') }}">
                                                                <i class="fas fa-ban d-md-none"></i>
                                                                <span class="d-none d-md-inline">{{ __('app.permissions.state_deny') }}</span>
                                                            </label>
                                                        </div>
                                                        <div class="perm-card__body mt-2">
                                                            @if($permission->icon)
                                                                <i class="fas {{ $permission->icon }} perm-card__icon"></i>
                                                            @endif
                                                            <span class="perm-card__title">{{ $permission->display_name }}</span>
                                                            <span class="perm-card__key">{{ $key }}</span>
                                                            @if($permission->description)
                                                                <span class="perm-card__desc">{{ $permission->description }}</span>
                                                            @endif
                                                            <div class="perm-access-status @if($effective) is-allowed @else is-denied @endif"
                                                                 data-role-granted="{{ $roleGranted ? '1' : '0' }}">
                                                                @if($overrideState === '1')
                                                                    <i class="fas fa-user-check"></i> {{ __('app.permissions.access_custom_allow') }}
                                                                @elseif($overrideState === '0')
                                                                    <i class="fas fa-user-slash"></i> {{ __('app.permissions.access_custom_deny') }}
                                                                @elseif($effective)
                                                                    <i class="fas fa-check-circle"></i> {{ __('app.permissions.access_from_role') }}
                                                                @else
                                                                    <i class="fas fa-times-circle"></i> {{ __('app.permissions.access_not_allowed') }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
