import Layout from '../../components/storefront/Layout.jsx';
import { useStorefront } from '../../components/storefront/StorefrontProvider.jsx';
import { ProductCard, ProductDetail } from '../../components/storefront/Products.jsx';

function Related() {
    const { related, wishlistOnly, wishlist, wishlistKey, copy: { pdp: p } } = useStorefront();
    if (!related.length) return null;
    const displayed = wishlistOnly ? related.filter(product => wishlist.includes(wishlistKey(product))) : related;
    return <section className="related-section section-shell"><div className="products-heading"><div><span className="eyebrow">{p.discover}</span><h2>{p.related_title}</h2></div></div><div className="product-grid">{displayed.map(product => <ProductCard key={product.id} product={product} related />)}</div></section>;
}
export default function Show(props) {
    return <Layout {...props}><ProductDetail product={props.product} /><Related /></Layout>;
}
