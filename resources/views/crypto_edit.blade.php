<!DOCTYPE html>
<html lang="zxx">

<head>
    <title>Intersperse Finance | About Us</title>
    @include('include.home_css')
</head>

<body>

    @include('include.home_header')

    <div class="breadcrumb-wrap position-relative index-1 bg-title">
        <div class="br-bg br-bg-1 position-absolute top-0 end-0 md-none"></div>
        <img src="{{ asset('build/assets/img/breadcrumb/br-shape-2.webp') }}" alt="Image"
            class="br-shape-two position-absolute">
        <div class="container position-relative">
            <img src="{{ asset('build/assets/img/breadcrumb/br-shape-1.webp') }}" alt="Image"
                class="br-shape-one position-absolute md-none">
            <img src="{{ asset('build/assets/img/breadcrumb/br-shape-3.webp') }}" alt="Image"
                class="br-shape-three position-absolute md-none">
            <div class="row">
                <div class="col-xxl-6 col-xl-6 col-lg-6">
                    <ul class="br-menu list-unstyle d-inline-block">
                        <li><a href="{{ route('app.home') }}">Home</a></li>
                        <li>Cryptocurrencies</li>
                    </ul>
                    <h2 class="br-title fw-medium text-white mb-0">Expert cryptocurrency investment and asset management
                        solutions  </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="pt-90 ">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-3 col-md-6">
                    <div class="pricing-card position-relative style-two transition index-1 mb-30">
                        <h5 class="price-tag transition" style="font-size: 24px;">$250,000<span
                                class="text-para f-primary fs-15 transition">/MIN</span></h2>
                        <h5 class="fw-semibold transition">Short Term Plan</h5>
                        <!-- <p class="transition">$500,000: MAX</p> -->
                        <h6 class="fs-16 f-primary transition">What's included?</h6>
                        <ul class="pricing-features list-unstyle">
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">12%:  Weekly ROI
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">10%:  Referral Bonus
                            </li>
                            <li class="position-relative transitione"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Duration:  1 Months</li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Personal Account Manager
                            </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">Full Access Over Your Money
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">24/7 Customer Support
                        </li>
                        </ul>
                        <a href="{{ route("user.register") }}" class="btn style-one d-block w-100">Get Started Now</a>
                    </div>
                </div>
                <!-- <div class="col-xl-3 col-md-6">
                    <div class="pricing-card featured position-relative style-two transition index-1 mb-30">
                        <h2 class="price-tag transition">$5,000<span
                                class="text-para f-primary fs-15 transition">/MIN</span></h2>
                        <h5 class="fw-semibold transition">Open Balance Plan</h5>
                        <p class="transition">$19,999: MAX</p>
                        <h6 class="fs-16 f-primary transition">What's included?</h6>
                        <ul class="pricing-features list-unstyle">
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">10%:  Monthly ROI
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">10%:  Referral Bonus
                            </li>
                            <li class="position-relative transitione"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Duration:  12 Months</li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Personal Account Manager
                            </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">Full Access Over Your Money
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">24/7 Customer Support
                        </li>
                        </ul>
                        <a href="{{ route("user.register") }}" class="btn style-one d-block w-100">Get Started Now</a>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="pricing-card position-relative style-two transition index-1 mb-30">
                        <h2 class="price-tag transition">$10,000<span
                                class="text-para f-primary fs-15 transition">/MIN</span></h2>
                        <h5 class="fw-semibold transition">Locked Balance Plan</h5>
                        <p class="transition">$19,999: MAX</p>
                        <h6 class="fs-16 f-primary transition">What's included?</h6>
                        <ul class="pricing-features list-unstyle">
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">12%:  Monthly ROI
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">10%:  Referral Bonus
                            </li>
                            <li class="position-relative transitione"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Duration:  12 Months</li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Personal Account Manager
                            </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">Full Access Over Your Money
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">24/7 Customer Support
                        </li>
                        </ul>
                        <a href="{{ route("user.register") }}" class="btn style-one d-block w-100">Get Started Now</a>
                    </div>
                </div> -->
                <div class="col-xl-3 col-md-6">
                    <div class="pricing-card position-relative style-two transition index-1 mb-30">
                        <h5 class="price-tag transition" style="font-size: 24px;">$500,000<span
                                class="text-para f-primary fs-15 transition">/MIN</span></h2>
                        <h5 class="fw-semibold transition">Long Term Plan</h5>
                        <!-- <p class="transition">$500,000: MAX</p> -->
                        <h6 class="fs-16 f-primary transition">What's included?</h6>
                        <ul class="pricing-features list-unstyle">
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">15%:  Weekly ROI
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">10%:  Referral Bonus
                            </li>
                            <li class="position-relative transitione"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Duration:  6 Months</li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                    alt="Image" class="position-absolute start-0 transition">Personal Account Manager
                            </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">Full Access Over Your Money
                        </li>
                            <li class="position-relative transition"><img src="{{ asset("build/assets/img/icons/check.svg") }}"
                                alt="Image" class="position-absolute start-0 transition">24/7 Customer Support
                        </li>
                        </ul>
                        <a href="{{ route("user.register") }}" class="btn style-one d-block w-100">Get Started Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container ptb-130">
        <div class="row">
            <div class="col-xxl-9 col-xl-8">
                <div class="service-desc">
                    <div class="single-img">
                        <img src="{{ asset('build/assets/img/services/crypto.png') }}" alt="Image">
                    </div>
                    <div class="single-para">
                        <h1>Transform Your Crypto Portfolio with Intersperse Finance</h1>
                        <p>Expert cryptocurrency investment and asset management solutions to grow your wealth securely
                            and efficiently. Start your journey today by exploring our innovative financial services
                            designed to meet all your investment needs. Join a community of savvy investors who trust us
                            to guide them through the complex world of digital assets and traditional investment
                            vehicles.

                        </p>
                        <p>Explore our range of services that cater to various investment goals and risk appetites.
                            Whether you're looking to invest in cryptocurrencies, manage your assets effectively, or
                            diversify your portfolio, Intersperse Finance has the expertise and tools to help you
                            succeed. Our services are designed to provide you with tailored strategies that maximize
                            returns while minimizing risks.

                        </p>
                        <p>Invest in the leading cryptocurrencies with guidance from our experts. We offer secure and
                            profitable growth opportunities in the fast-evolving digital asset market. Our team stays
                            ahead of market trends to provide you with the best investment options, ensuring your
                            portfolio remains robust and future-proof.

                        </p>
                    </div>
                    <div class="single-feature-box bg-optional round-2 mb-35">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="feature-item position-relative mb-25">
                                    <img src="{{ asset('build/assets/img/icons/check.svg') }}" alt="Image"
                                        class="position-absolute start-0">
                                    <h4>Asset Management</h4>
                                    <p class="mb-0">Our seasoned professionals manage your assets with precision and
                                        care, providing tailored strategies that align with your financial goals. p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-item position-relative mb-25">
                                    <img src="{{ asset('build/assets/img/icons/check.svg') }}" alt="Image"
                                        class="position-absolute start-0">
                                    <h4>Portfolio Management</h4>
                                    <p class="mb-0">Experience personalized portfolio management that diversifies your
                                        investments to achieve long-term financial goals.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="single-para">
                        <h6>Why Intersperse Finance?</h6>
                        <p>Trusted by investors worldwide, Intersperse Finance offers innovative financial solutions
                            backed by expertise and transparency. Our commitment to excellence and client satisfaction
                            sets us apart in the industry. Discover the reasons why our clients choose us for their
                            investment needs and experience the difference of working with a dedicated and knowledgeable
                            team.</p>
                        <p>Our team of financial experts brings years of experience in cryptocurrency and asset
                            management. We leverage our deep understanding of financial markets and cutting-edge
                            technologies to provide you with the best investment advice and services. Our expertise is
                            your advantage in achieving financial success.</p>
                    </div>
                    <div class="row">
                        <div class="col-md-7">
                            <div class="single-img">
                                <img src="{{ asset('build/assets/img/services/crypto1.png') }}"
                                    alt="Image">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="single-img">
                                <img src="{{ asset('build/assets/img/services/single-service-3.webp') }}"
                                    alt="Image">
                            </div>
                        </div>
                    </div>
                    <div class="single-para">
                        <h5>Diversifying Your Portfolio: Why It Matters</h5>
                        <p>Understand the importance of diversification in achieving long-term financial stability. Discover strategies to balance your investments and minimize risks. Learn essential tips to start investing in cryptocurrencies effectively. Our expert advice helps you navigate the volatile crypto market with confidence and knowledge.

                        </p>
                        <p>Discover how professional asset management can optimize your investment returns. Our detailed insights guide you toward making informed decisions for sustained financial growth.</p>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-xl-4">
                <div class="sidebar style-two">
                    <div class="sidebar-widget bg-optional">
                        <h3 class="sidebar-widget-title fs-24 fw-medium text-title">Our Services</h3>
                        <ul class="service-list list-unstyle">
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto Trading
                                     <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a></li>
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto Asset Management
                                     <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a>
                            </li>
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto Portfolio
                                    Management <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a></li>
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto
                                    Aquisition consulting <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a>
                            </li>
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto Invest
                                    Planning <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a></li>
                            <li class="text-title fw-bold"><a  class="d-block w-100">Crypto Investment
                                    Mentorship <img src="{{ asset('build/assets/img/icons/long-arrow-blue.svg') }}"
                                        alt="Image"></a>
                            </li>
                        </ul>
                    </div>
                    <div class="sidebar-widget bg-optional">
                        <h3 class="sidebar-widget-title fs-24 fw-medium text-title">Get A Free Quote</h3>
                        <form action="#" class="contact-widget">
                            <div class="form-group mb-15">
                                <input type="text" placeholder="Your name" class="fs-15 h-42 w-100 bg-trasnparent">
                            </div>
                            <div class="form-group mb-15">
                                <input type="email" placeholder="Your email" class="fs-15 h-42 w-100 bg-trasnparent">
                            </div>
                            <div class="form-group mb-20">
                                <textarea name="" id="" cols="30" rows="10"
                                    class="fs-15 h-160 resize-0 w-100 bg-trasnparent" placeholder="Your Messages"></textarea>
                            </div>
                            <button class="btn style-one">Send Your Message</button>
                        </form>
                    </div>
                    <div class="sidebar-widget bg-optional">
                        <h3 class="sidebar-widget-title fs-24 fw-medium text-title text-center">If You Need Any Help
                            Contact With Us</h3>
                        <ul class="contact-info text-center list-unstyle">
                            <li class="fs-20 fw-bold f-secondary text-title d-inline-block position-relative"><span
                                    class="d-flex flex-coulmn align-items-center justify-content-center rounded-circle bg_secondary text-white position-absolute start-0"><i
                                        class="ri-phone-fill"></i></span><a href="tel:+19015576228">+1 (901) 557 - 6228</a></li>
                            <li class="fs-20 fw-bold f-secondary text-title d-inline-block position-relative"><span
                                    class="d-flex flex-coulmn align-items-center justify-content-center rounded-circle bg_secondary text-white position-absolute start-0"><i
                                        class="ri-message-2-fill"></i></span><a href="#"><span
                                        class="__cf_email__" style="font-size: 14px">support@intersperse.net</span></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <h1 style="text-align: center; margin-top: 70px">CRYPTO INVESTMENT PLANS</h1>
    
   
    </div>

    @include('include.home_how_it_works')

    @include('include.home_footer')
    @include('include.home_js')
</body>

</html>
