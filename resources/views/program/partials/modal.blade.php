@switch($kind)
    @case('detail')
        <x-lop-detail-modal :id="$modalId" :lop="$lop" />
        @break
    @case('tracking')
        <x-lop-tracking-modal :id="$modalId" :lop="$lop" />
        @break
    @case('assign')
        <x-assign-technician-modal :id="$modalId" :lop="$lop" :technicians="$technicians" :return-to="$returnTo" :return-url="$returnUrl" />
        @break
    @case('request-letter')
        <x-request-letter-modal :id="$modalId" :lop="$lop" />
        @break
@endswitch
