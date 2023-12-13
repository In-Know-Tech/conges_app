<?php

namespace App\Http\Controllers\Website;

use App\Models\Faq;
use App\Models\Seo;
use App\Models\Plan;
use App\Models\Post;
use AmrShawky\Currency;
use App\Models\Feature;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Artesaos\SEOTools\Facades\SEOMeta;
use App\Services\Midtrans\CreateSnapTokenService;
use App\Traits\PaymentAble;
use Artesaos\SEOTools\Traits\SEOTools as SEOToolsTrait;

//use App\Models\Faq;
//use App\Models\Feature;
//use App\Models\Testimonial;
//use App\Models\Plan;
//use App\Models\Post;
//use App\Services\CreateSnapTokenService;
//use Illuminate\Support\Facades\SEOMeta;

class WebsiteController extends Controller
{
    use SEOToolsTrait, PaymentAble;

    public function home()
    {
        $this->getPageMetaContent('home');

        $faqs = Faq::all()->get();
        $features = Feature::all()->get();
        $testimonials = Testimonial::all()->get();
        $plans = Plan::all('planFeatures')->whereStatus(1)->get();

        return view('website.home', compact('faqs', 'features', 'testimonials', 'plans'));
    }

    public function about()
    {
        $this->getPageMetaContent('about');

        $testimonials = Testimonial::all();

        return view('website.about', compact('testimonials'));
    }

    public function pricing()
    {
        $this->getPageMetaContent('pricing');

        $faqs = Faq::all()->get();
        $plans = Plan::with('planFeatures')->whereStatus(1)->get();

        return view('website.pricing', compact('faqs', 'plans'));
    }

    public function blog()
    {
        $this->getPageMetaContent('blog');

        $posts = Post::with('user')
            ->latest()
            ->paginate(12, ['id', 'title', 'slug', 'thumbnail', 'short_description']);

        return view('website.blog', compact('posts'));
    }

    public function blogDetails(Post $post)
    {
        $this->seo()->setTitle($post->title);
        $this->seo()->setDescription($post->short_description);
        $this->seo()->opengraph()->setUrl(url()->current());
        $this->seo()->opengraph()->addProperty('type', 'website');
        $this->seo()->twitter()->setSite(url()->current());
        $this->seo()->jsonLd()->setType('Website');

        $post->increment('total_views');
        $post->load('user');
        $popular_posts = Post::popular()->limit(4)->get();
        $latest_posts = Post::latestExcept($post->id)->limit(3)->get();

        return view('website.blog-details', compact(
            'post',
            'popular_posts',
            'latest_posts'
        ));
    }

    public function contact()
    {
        $this->getPageMetaContent('contact');

        return view('website.contact');
    }

    public function planDetails(Plan $plan)
    {
        $this->authorize('view', $plan);

        $this->getPageMetaContent('pricing');

        // flash data storing
        flash()->put('plan', $plan);
        flash()->put('stripe_amount', currencyConversion($plan->price) * 100);
        flash()->put('razor_amount', currencyConversion(50, null, 'INR', 1) * 100);

        // midtrans snap token
        if (config('kodebazar.midtrans_active') && config('kodebazar.midtrans_id') && config('kodebazar.midtrans_key') && config('kodebazar.midtrans_secret')) {
            $midtrans_amount = round(currencyConversion($plan->price, null, 'IDR', 1));
            $order_id = uniqid();

            flash()->put('midtrans_amount', $midtrans_amount);
            flash()->put('midtrans_order_id', $order_id);

            $order['order_no'] = $order_id;
            $order['total_price'] = $midtrans_amount;

            $midtrans = new CreateSnapTokenService($order);
            $snapToken = $midtrans->getSnapToken();
        }

        return view('website.plan_details', [
            'plan' => $plan,
            'mid_token' => $snapToken ?? null,
        ]);
    }

    public function privacyPolicy()
    {
        $this->getPageMetaContent('privacy-policy');

        return view('website.privacy_policy');
    }

    public function termsCondition()
    {
        $this->getPageMetaContent('terms-conditions');

        return view('website.terms_condition');
    }

    private function getPageMetaContent($pageName)
    {
        $content = metaContent($pageName);
        $this->seo()->setTitle($content->title);
        $this->seo()->setDescription($content->description);
        SEOMeta::setKeywords($content->keywords);
        $this->seo()->opengraph()->setUrl(url()->current());
        $this->seo()->opengraph()->addProperty('type', 'website');
        $this->seo()->twitter()->setSite(url()->current());
        $this->seo()->jsonLd()->setType('Website');
    }
}
