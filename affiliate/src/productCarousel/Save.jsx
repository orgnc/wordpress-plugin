import { useBlockProps } from '@wordpress/block-editor';

import ProductCarousel from './ProductCarousel';
import { AttributesType } from './propTypes';

const Save = ({ attributes }) => (
  // eslint-disable-next-line react/jsx-props-no-spreading
  <div {...useBlockProps.save()}>
    {attributes?.productCarouselSnippet && (
      <ProductCarousel
        productCarouselSnippet={attributes.productCarouselSnippet}
      />
    )}
  </div>
);

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
